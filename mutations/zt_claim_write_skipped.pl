# ZT_claim_write_skipped: claim_download() never writes the claim: the archive can be handed over again.
s#\$claimed = self::update_if_status\(#\$claimed = true || self::update_if_status(#s;
