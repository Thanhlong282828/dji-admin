# Process supervision

Docker Compose runs queue, scheduler, Horizon and Reverb as isolated services. Production orchestration should restart each service independently; a process supervisor is intentionally not embedded in the PHP-FPM image.
