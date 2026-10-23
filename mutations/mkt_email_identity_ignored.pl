# MKT_email_identity_ignored: email_visible() never asks core's identity fields.
s#if \(!in_array\('email', \\core_user\\fields::get_identity_fields\(\$context, false\), true\)\) \{#if (false) {#s;
