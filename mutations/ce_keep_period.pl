# CE_keep_period: Every row is older than the keep period.
s#\['before' => \$now - self::KEEP_FOR\]#['before' => \$now + DAYSECS]#s;
