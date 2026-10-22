# SC_manager_capability: Nobody is a manager: the `:manage` capability is never read.
s#\$manager = has_capability\('block/download_certificates:manage', \\context_system::instance\(\), \$userid\);#\$manager = false;#s;
