<?php
/**
 * Shared Google-account lookup/creation used by web and native sign-in flows.
 */

function jyutdictFindOrCreateGoogleUser($dbh, $googleId, $email, $nickname) {
    $googleId = trim((string)$googleId);
    $email = trim((string)$email);
    $nickname = trim((string)$nickname);

    if ($googleId === '') {
        throw new InvalidArgumentException('Missing Google account identifier');
    }

    $stmt = $dbh->prepare(
        "SELECT `id`, `email`, `nickname`, `role`, `created_at`
         FROM `users`
         WHERE `google_id` = :google_id"
    );
    $stmt->execute([':google_id' => $googleId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        try {
            $stmt = $dbh->prepare(
                "INSERT INTO `users` (`google_id`, `email`, `nickname`, `role`)
                 VALUES (:google_id, :email, :nickname, 'user')"
            );
            $stmt->execute([
                ':google_id' => $googleId,
                ':email' => $email,
                ':nickname' => $nickname !== '' ? $nickname : null,
            ]);
        } catch (PDOException $exception) {
            // A concurrent first login may have inserted the same unique Google ID.
            $retry = $dbh->prepare("SELECT `id` FROM `users` WHERE `google_id` = :google_id");
            $retry->execute([':google_id' => $googleId]);
            if (!$retry->fetch(PDO::FETCH_ASSOC)) {
                throw $exception;
            }
        }

        $stmt = $dbh->prepare(
            "SELECT `id`, `email`, `nickname`, `role`, `created_at`
             FROM `users`
             WHERE `google_id` = :google_id"
        );
        $stmt->execute([':google_id' => $googleId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        throw new RuntimeException('Unable to load Google user');
    }

    $user['id'] = (int)$user['id'];
    return $user;
}
