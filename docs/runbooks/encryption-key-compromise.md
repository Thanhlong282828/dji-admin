# Encryption Key Compromise Runbook

Do not rotate the production application key ad hoc. Isolate the affected credential path, identify dependent ciphertext by metadata/reference only, activate the approved old/new-key compatibility procedure, rotate provider credentials first where applicable, re-encrypt in bounded verified batches, validate rollback/decryption, cut over, and retire the old key only after certification. Never print keys or ciphertext in tickets, logs or evidence.
