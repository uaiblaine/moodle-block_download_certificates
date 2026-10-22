# FIL_unknown_keys: from_array() walks the input keys, so unknown keys are passed on to the constructor.
s#foreach \(self::KEYS as \$key => \$type\) \{#foreach (\$data as \$key => \$ignored) {
            \$type = self::KEYS[\$key] ?? PARAM_TEXT;#s;
