# REQ_range_equal: A range whose end equals its start is accepted (boundary of the order check).
s#\$filter->dateto <= \$filter->datefrom#\$filter->dateto < \$filter->datefrom#s;
