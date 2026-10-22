# SC_own_certificates: The user's own certificates are no longer an alternative.
s#\$alternatives = \["\{\$useridcol\} = :\{\$p\}own"\];#\$alternatives = ["{\$useridcol} = :{\$p}own AND 1 = 0"];#s;
