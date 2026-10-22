# TS_owns_path_base_itself: owns_path() accepts the base directory itself (prefix without the separator).
s#str_starts_with\(\$real, \$base \. DIRECTORY_SEPARATOR\)#str_starts_with(\$real, \$base)#s;
