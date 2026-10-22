# REQ_range_order: A range that ends before it starts, or has no start, is accepted.
s#if \(!\$filter->datefrom \|\| \$filter->dateto <= \$filter->datefrom\) \{#if (false) {#s;
