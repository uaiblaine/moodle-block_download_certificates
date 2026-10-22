# CE_stuck_age: Every pending or processing row is failed as stuck, whatever its age.
s#status IN \(:pending, :processing\) AND timemodified < :before#status IN (:pending, :processing) AND :before > 0#s;
