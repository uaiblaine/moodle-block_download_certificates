# TS_owns_path_unresolved: owns_path() compares the path as given: '..' segments and missing files pass.
s#: realpath\(\$path\);#: \$path;#s;
