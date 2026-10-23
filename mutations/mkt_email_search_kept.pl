# MKT_email_search_kept: search_page() still matches the search text against a hidden e-mail.
s#\$filter = \$filter->without_email_search\(\);##s;
