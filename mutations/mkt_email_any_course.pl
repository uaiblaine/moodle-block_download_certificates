# MKT_email_any_course: one course that allows identities shows the e-mail of every course.
s#if \(!in_array\('email', (\\core_user\\fields::get_identity_fields\(\$context, false\)), true\)\) \{\n(\s+)return false;\n(\s+)\}\n(\s+)\}\n(\s+)return true;#if (in_array('email', $1, true)) {\n$2return true;\n$3}\n$4}\n$5return false;#s;
