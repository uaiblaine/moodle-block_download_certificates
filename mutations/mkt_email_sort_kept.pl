# MKT_email_sort_kept: search_page() still sorts by a hidden e-mail.
s#if \(\$sort === 'email'\) \{\n(\s+)\$sort = 'timecreated';#if (\$sort === 'email') {\n$1\$sort = 'email';#s;
