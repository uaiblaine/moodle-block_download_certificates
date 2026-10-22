# REQ_manager_types: A non-manager may ask for the manager-only types (all, cohort, range).
s#if \(in_array\(\$type, self::MANAGER_TYPES, true\) && !\$scope->is_manager\(\)\) \{#if (false) {#s;
