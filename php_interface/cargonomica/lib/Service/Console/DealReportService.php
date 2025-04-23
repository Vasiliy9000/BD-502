
<?php

namespace Cargonomica\Service\Console;

use Bitrix\Crm\ContactTable;
use Bitrix\Crm\DealTable;
use Bitrix\Crm\FieldMultiTable;
use Bitrix\Main\Application;
use Bitrix\Main\ArgumentException;
use Bitrix\Main\ArgumentTypeException;
use Bitrix\Main\Db\SqlQueryException;
use Bitrix\Main\Mail\Event;
use Bitrix\Main\ObjectPropertyException;
use Bitrix\Main\SystemException;
use Bitrix\Main\UserTable;
use Cargonomica\Exception\LoggerException;
use Cargonomica\Helper\PhoneHelper;
use Cargonomica\Logger\DefaultLogger;
use Cargonomica\Service\ModuleOptions\CargonomicaMainOptionsService;
use CFile;
use DateTime;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

/**
 * Сервис отправки отчета о сделаках на электронную почту
 */
class DealReportService extends DefaultConsoleService
{
    /**
     * Стадия воронки, по которой нужно сформировать отчет
     * @var string
     */
    protected string $stage;

    /**
     * Адрес электронной почты, куда нужно отправить отчет
     * @var string
     * Адреса электронной почты, куда нужно отправить отчет
     * @var array
     */
    protected string $receiver;
    protected array $receivers;

    /**
     * Начало периода, за который нужно сформировать отчет
     * @var DateTime
     */
    protected DateTime $from;

    /**
     * Окончание периода, за который нужно сформировать отчет
     * @var DateTime
     */
    protected DateTime $to;
    /**
     * Конструктор класса. Устанавливает значения свойств, переданных в качестве параметров при создании экземпляра класса
     * @param string $stage
     * @param string $receiver
     * @param DateTime $from
     * @param DateTime $to
     * @throws LoggerException
     */
    public function __construct(string $stage, string $receiver, DateTime $from, DateTime $to)
    public function __construct(string $stage, DateTime $from, DateTime $to)
    {
        parent::__construct();
        $this->stage = $stage;
        $this->receiver = $receiver;
        $receiversString = CargonomicaMainOptionsService::getOption("report_emails");
        $this->receivers = array_map(
            function ($email) {
                return trim($email);
            }, explode(",", $receiversString));
        $this->from = $from;
        $this->to = $to;
    }

    /**
     * Метод возвращает массив сделок, перешедших в этап воронки в интервал времени
     * @param string $stageId ID этапа воронки, о сделках которой нужно сделать отчет
     * @param DateTime $startDate Начало периода отчета
     * @param DateTime $endDate Окончание периода отчета периода отчета
     * @return array Массив, содержащий сделки
     * @throws ArgumentException
     * @throws SystemException
     * @throws ObjectPropertyException
     */
    protected static function getNewDealsInStage(string $stageId, DateTime $startDate, DateTime $endDate): array
    {
        $result = [];
        $deals=DealTable::getList([
            "select" => [
                'ID',
                'STAGE_ID',
                'CLOSEDATE',
                'UF_DEAL_CONTACTS',
                'ASSIGNED_BY_ID',
            ],
            "filter" => [
                '>=CLOSEDATE' => $startDate->format('d.m.Y H:i:s'),
                '<=CLOSEDATE' => $endDate->format('d.m.Y H:i:s'),
                '=STAGE_ID' => $stageId,
            ]
        ]);
        while ($deal = $deals->fetch()) {
            $contactID = $deal['UF_DEAL_CONTACTS'];
            $contactData = ContactTable::getList([
                'select' => ['ID', 'NAME', 'LAST_NAME', 'PHONE'],
                'filter' => ['ID' => $contactID],
                'limit' => 1
            ])->fetch();

            $phones = FieldMultiTable::getList([
                'select' => ['*'],
                'filter' => ['ELEMENT_ID' => $contactID, "ENTITY_ID" => "CONTACT", "TYPE_ID" => "PHONE"],
            ])->fetchAll();
            $userData = UserTable::getList([
                'select' => ['ID', 'NAME', 'LAST_NAME'],
                'filter' => ['ID' => $deal['ASSIGNED_BY_ID']],
                'limit' => 1
            ])->fetch();
            if (!empty($phones)) {
                $result[] = [
                    'contactName' => $contactData['NAME'],
                    'contactLastName' => $contactData['LAST_NAME'],
                    'closeDate' => $deal['CLOSEDATE']->format('d.m.Y'),
                    'phone' => static::getLastCallNumber(array_column($phones, "VALUE")),
                    'userName' => $userData['NAME'],
                    'userLastName' => $userData['LAST_NAME'],
                ];
            }
        }
        return $result;
    }

    /**
     * Возвращает номер телефона на который был последний звонок
     * @param $phones
     * @return string
     * @throws SqlQueryException
     */
    public static function getLastCallNumber($phones): string
    {
        $phonesList = implode("','", array_map(function($phone) {
            return PhoneHelper::toInternationalFormat($phone);
        }, $phones));

        $sql = <<<SQL
            SELECT PHONE_NUMBER
            FROM b_voximplant_statistic
            WHERE INCOMING = '1'
            AND ((
                    LENGTH(REGEXP_REPLACE(PHONE_NUMBER, '[^0-9]', '')) = 11 AND
                    LEFT(REGEXP_REPLACE(PHONE_NUMBER, '[^0-9]', ''), 1) IN ('7', '8') AND
                    CONCAT('7', SUBSTRING(REGEXP_REPLACE(PHONE_NUMBER, '[^0-9]', ''), 2)) IN ('$phonesList'))
                OR REGEXP_REPLACE(PHONE_NUMBER, '[^0-9]', '') IN ('$phonesList'))
            ORDER BY CALL_START_DATE DESC
            LIMIT 1;
        SQL;
        $connection = Application::getConnection();
        $result = $connection->query($sql)->fetch();
        return empty($result) ? PhoneHelper::toReadableFormat($phones[0]) : PhoneHelper::toReadableFormat($result['PHONE_NUMBER']);
    }

    /**
     * Метод отправляет на email CSV-отчет со сделками, перешедшими в этап воронки в интервал времени
     * @return void Результат отправки сообщения
     * @throws ArgumentException
     * @throws ObjectPropertyException
     * @throws SystemException
     * @throws ArgumentTypeException|LoggerException
     */
    public function execute(): void
    {
        if (CargonomicaMainOptionsService::getOption("report_send_daily") == "N") {
            $this->logger->info("Формирование отчёта отключено в настройках модуля");
            return;
        }
        if (empty($this->receivers)) {
            $this->logger->error("Массив адресов электронной почты пуст");
            return;
        }
        $reportData = self::getNewDealsInStage($this->stage, $this->from, $this->to);
        if (empty($reportData)) {
            $this->logger->info("Закрытых сделок не найдено");
            return;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'Имя ЛПР');
        $sheet->setCellValue('B1', 'Фамилия ЛПР');
        $sheet->setCellValue('C1', 'Дата отгрузки\завершения сделки');
        $sheet->setCellValue('D1', 'Телефон ЛПР');
        $sheet->setCellValue('E1', 'Филиал');
        $sheet->setCellValue('F1', 'Фамилия и имя ответственного');
        foreach ($reportData as $rowIndex => $row) {
            $sheet->setCellValue("A" . $rowIndex + 2, $row["contactName"]);
            $sheet->setCellValue("B" . $rowIndex + 2, $row["contactLastName"]);
            $sheet->setCellValue("C" . $rowIndex + 2, $row["closeDate"]);
            $sheet->setCellValue("D" . $rowIndex + 2, $row["phone"]);
            $sheet->setCellValue("E" . $rowIndex + 2, "Бизнес-парк \"Румянцево\"");
            $sheet->setCellValue("F" . $rowIndex + 2, $row["userName"] . " " . $row["userLastName"]);
        }
        $writer = new Csv($spreadsheet);
        $writer->setDelimiter(';');
        ob_start();
        $writer->save('php://output');
        $csvContents = ob_get_clean();
        $fileId = CFile::SaveFile([
            "name" => "report.csv",
            "type" => "application/octet-stream",
            "content" => $csvContents,
            "description" => "Ежедневный отчет",
            "MODULE_ID" => "main",
        ], "reports");
        Event::send([

        $result = Event::sendImmediate([
            'EVENT_NAME' => 'SEND_REPORT_WITH_ATTACHMENT',
            'LID' => 's1',
            'C_FIELDS' => [
                "EMAIL_TO" => $this->receiver,
                "EMAIL_TO" => implode(',', $this->receivers),
                "EMAIL_SUBJECT" => "Отчет по сделкам",
                "EMAIL_BODY" => "Прикреплен CSV-файл с отчетом по сделкам",
            ],
            'FILE' => [$fileId]
        ]);

        if ($fileId) {
            CFile::Delete($fileId);
        }

        $timestamp = (new DateTime('now'))->format("Y-m-d H:i:s");
        $dealsCount = count($reportData);
        $logData = [];
        $logData['context'] = [
            'dealsCount' => $dealsCount,
            'receivers' => $this->receivers
        ];
        switch ($result) {
            case Event::SEND_RESULT_SUCCESS:
                CargonomicaMainOptionsService::setOptions([
                    'last_report_timestamp' => $timestamp,
                    'last_report_deals_count' => $dealsCount,
                ]);
                $logData['message'] = "Отчёт успешно отправлен всем получателям";
                $logData['level'] = DefaultLogger::LOG_LEVEL_INFO;
                break;
            case Event::SEND_RESULT_PARTLY:
                CargonomicaMainOptionsService::setOptions([
                    'last_report_timestamp' => $timestamp,
                    'last_report_deals_count' => $dealsCount,
                ]);
                $logData['message'] = "Возникли проблемы при отправке отчёта некоторым получателям";
                $logData['level'] = DefaultLogger::LOG_LEVEL_ERROR;
                break;
            case Event::SEND_RESULT_ERROR:
                $logData['message'] = "Не удалось отправить отчёт ни одному получателю";
                $logData['context']['receivers'] = $this->receivers;
                $logData['level'] = DefaultLogger::LOG_LEVEL_CRITICAL;
                break;
        }

        $this->logger->log($logData['message'], $logData['context'], $logData['level']);
    }
}
