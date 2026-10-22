# CE_ready_age: Every ready archive expires, whatever its age.
s#status = :ready AND timemodified < :before#status = :ready AND :before > 0#s;
