# RTO and RPO Governance

RTO/RPO have three distinct states: configured target, observed testing result and approved production objective. A testing certificate never promotes itself to an official production SLA.

Measurements use dependency stop/detection/restart/readiness timestamps. RPO compares the latest committed synthetic fixture point with the restored point using deterministic checksums. Production objectives require infrastructure/provider evidence, retention approval, backup cadence and PITR capability.
