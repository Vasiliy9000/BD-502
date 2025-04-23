(new DealReportService(
    VEHICLE_HAS_LEFT_PPRICEP_DS_ID,
  //  "info@okreview.ru", удалённая строка
    (new DateTime('now'))->modify('-1 day')->setTime(0, 0, 0),
    (new DateTime('now'))->modify('-1 day')->setTime(23, 59, 59),)
)->execute();
