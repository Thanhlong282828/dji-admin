# APP_KEY rotation

Do not rotate directly. Inventory encrypted database fields, cookies, sessions and artifacts; implement and test dual-key decryption; back up safely; deploy reader compatibility; re-encrypt in bounded testing; cut over; verify; then retire the old key. If any ciphertext cannot decrypt, stop and roll back the application key reference.
