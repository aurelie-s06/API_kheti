<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Reservations;
use App\Users;
use Doctrine\ORM\EntityManager;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// fonctions utilitaires

function out(int $code, array $data): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) out(400, ['error' => 'JSON invalide.']);
    return $data;
}

function b64e(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function b64d(string $d): string
{
    $d .= str_repeat('=', (4 - strlen($d) % 4) % 4);
    $r = base64_decode(strtr($d, '-_', '+/'), true);
    if ($r === false) out(401, ['error' => 'Token invalide.']);
    return $r;
}

function apiSecret(): string
{
    $s = getenv('BACKOFFICE_API_SECRET');
    return (is_string($s) && $s !== '') ? $s : 'change_this_secret_in_env';
}

function isLocalRequest(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\\d+$/', '', $host);
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
        || in_array($remote, ['127.0.0.1', '::1'], true);
}

function makeToken(Users $u): string
{
    $h = b64e((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $now = time();
    $p = b64e((string) json_encode([
        'sub' => $u->getId(), 'email' => $u->getMail(),
        'admin_state' => $u->getAdminState(), 'iat' => $now, 'exp' => $now + 3600,
    ]));
    return "$h.$p." . b64e(hash_hmac('sha256', "$h.$p", apiSecret(), true));
}

function checkToken(string $token): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) out(401, ['error' => 'Token invalide.']);
    [$h, $p, $s] = $parts;
    if (!hash_equals(hash_hmac('sha256', "$h.$p", apiSecret(), true), b64d($s)))
        out(401, ['error' => 'Signature invalide.']);
    $payload = json_decode(b64d($p), true);
    if (!is_array($payload) || ($payload['exp'] ?? 0) < time())
        out(401, ['error' => 'Token expiré ou invalide.']);
    return $payload;
}

function userArr(Users $u): array
{
    return ['id' => $u->getId(), 'name' => $u->getNom(), 'first_name' => $u->getPrenom(),
            'email' => $u->getMail(), 'admin_state' => $u->getAdminState()];
}

function resArr(Reservations $r): array
{
    $peopleCount = $r->getAdultCount() + $r->getChildCount() + $r->getStudentCount();
    return ['id' => $r->getId(), 'day' => $r->getDay(), 'hour' => $r->getHour(),
            'price' => $r->getPrice(), 'adult_count' => $r->getAdultCount(),
            'child_count' => $r->getChildCount(), 'student_count' => $r->getStudentCount(),
            'number_of_people' => $peopleCount, 'email' => $r->getEmail()];
}

function countSlotsForDayAndHour(EntityManager $em, string $day, string $hour, ?int $excludeId = null): int
{
    $qb = $em->createQueryBuilder()
        ->select('SUM(r.adult_count + r.child_count + r.student_count)')
        ->from(Reservations::class, 'r')
        ->where('r.day = :day')
        ->andWhere('r.hour = :hour')
        ->setParameter('day', $day)
        ->setParameter('hour', $hour);
    
    if ($excludeId !== null) {
        $qb->andWhere('r.id_reservation != :excludeId')
           ->setParameter('excludeId', $excludeId);
    }
    
    $result = $qb->getQuery()->getSingleScalarResult();
    return (int) ($result ?? 0);
}

// routes

if (isset($_GET['resource'])) {
    $resource = trim((string) $_GET['resource']);
    $id       = trim((string) ($_GET['id'] ?? ''));
} else {
    $uri  = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '');
    $base = rtrim((string) dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');

    $path = $uri;
    if ($base !== '' && str_starts_with($path, $base . '/')) {
        $path = substr($path, strlen($base) + 1);
    }

    $path = trim($path, '/');
    if ($path === 'index.php') {
        $path = '';
    } elseif (str_starts_with($path, 'index.php/')) {
        $path = substr($path, strlen('index.php/'));
    }

    $seg  = explode('/', $path);
    $resource = trim($seg[0] ?? '');
    $id       = trim($seg[1] ?? '');
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($resource === '')
    out(200, ['endpoints' => ['GET /api/index.php/users', 'GET /api/index.php/reservations',
        'POST /api/index.php/auth']]);

if (!in_array($resource, ['auth', 'users', 'reservations', 'send-email'], true))
    out(404, ['error' => 'Ressource inconnue.']);


// auth

if ($resource === 'auth') {
    if ($method === 'POST') {
        $data = input();
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($email === '' || $password === '') {
            out(422, ['error' => 'Champs email et password requis.']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            out(422, ['error' => 'Email invalide.']);
        }

        $user = $entityManager->getRepository(Users::class)->find($email);
        if (!$user instanceof Users || !password_verify($password, $user->getMotDePasse())) {
            out(401, ['error' => 'Identifiants invalides.']);
        }

        out(200, ['data' => ['user' => userArr($user), 'token' => makeToken($user)]]);
    }

    out(405, ['error' => 'Méthode non autorisée.']);
}


// users 

if ($resource === 'users') {
    $repo = $entityManager->getRepository(Users::class);

    if ($method === 'GET' && $id === '')
        out(200, ['data' => array_map('userArr', $repo->findAll())]);

    if ($method === 'GET') {
        $user = $repo->find((string) $id);
        if (!$user instanceof Users) out(404, ['error' => 'Utilisateur introuvable.']);
        out(200, ['data' => userArr($user)]);
    }

    if ($method === 'POST') {
        $data = input();
        foreach (['name', 'first_name', 'email', 'password'] as $f)
            if (empty($data[$f])) out(422, ['error' => "Champ $f requis."]);

        $email = strtolower(trim((string) $data['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            out(422, ['error' => 'Email invalide.']);
        }

        if ($repo->find($email) instanceof Users) {
            out(409, ['error' => 'Un compte existe déjà avec cet email.']);
        }

        $user = (new Users())
            ->setNom(trim((string) $data['name']))
            ->setPrenom(trim((string) $data['first_name']))
            ->setMail($email)
            ->setMotDePasse(password_hash((string) $data['password'], PASSWORD_BCRYPT))
            ->setAdminState((int) ($data['admin_state'] ?? 0));
        $entityManager->persist($user);
        $entityManager->flush();
        out(201, ['data' => userArr($user)]);
    }

    if ($method === 'PUT') {
        $user = $repo->find((string) $id);
        if (!$user instanceof Users) out(404, ['error' => 'Utilisateur introuvable.']);
        $data = input();
        if (isset($data['name']))        $user->setNom(trim((string) $data['name']));
        if (isset($data['first_name']))  $user->setPrenom(trim((string) $data['first_name']));
        if (isset($data['email']))       $user->setMail(trim((string) $data['email']));
        if (!empty($data['password']))   $user->setMotDePasse(password_hash((string) $data['password'], PASSWORD_BCRYPT));
        if (isset($data['admin_state'])) $user->setAdminState((int) $data['admin_state']);
        $entityManager->flush();
        out(200, ['data' => userArr($user)]);
    }

    if ($method === 'DELETE') {
        $user = $repo->find((string) $id);
        if (!$user instanceof Users) out(404, ['error' => 'Utilisateur introuvable.']);
        $entityManager->remove($user);
        $entityManager->flush();
        out(200, ['message' => 'Utilisateur supprimé.']);
    }

    out(405, ['error' => 'Méthode non autorisée.']);
}

// reservations
if ($resource === 'reservations') {
    $repo = $entityManager->getRepository(Reservations::class);

    if ($method === 'GET' && $id === '') {
        out(200, ['data' => array_map('resArr', $repo->findAll())]);
    }

    if ($method === 'GET') {
        $res = $repo->find((int) $id);
        if (!$res instanceof Reservations) out(404, ['error' => 'Réservation introuvable.']);
        out(200, ['data' => resArr($res)]);
    }

    if ($method === 'POST') {
        $data = input();
        foreach (['day', 'hour', 'price', 'adult_count', 'child_count', 'student_count', 'email'] as $f)
            if (!array_key_exists($f, $data) || $data[$f] === '') out(422, ['error' => "Champ $f requis."]);

        foreach (['adult_count', 'child_count', 'student_count'] as $f) {
            if (!is_numeric($data[$f]) || (int) $data[$f] < 0) {
                out(422, ['error' => "$f doit être un entier positif ou nul."]);
            }
        }

        $email = strtolower(trim((string) $data['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) out(422, ['error' => 'email invalide.']);

        $dayStr = trim((string) $data['day']);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayStr)) out(422, ['error' => 'day doit être au format YYYY-MM-DD.']);

        $newPeopleCount = (int) $data['adult_count'] + (int) $data['child_count'] + (int) $data['student_count'];
        $hour = trim((string) $data['hour']);
        $occupiedSlots = countSlotsForDayAndHour($entityManager, $dayStr, $hour);

        if ($occupiedSlots + $newPeopleCount > 10) {
            $availableSlots = max(0, 10 - $occupiedSlots);
            out(422, ['error' => "Créneau plein. Seulement $availableSlots place(s) restante(s) pour ce créneau le $dayStr à $hour."]);
        }

        $res = (new Reservations())
            ->setDay($dayStr)->setHour($hour)
            ->setPrice(trim((string) $data['price']))
            ->setAdultCount((int) $data['adult_count'])
            ->setChildCount((int) $data['child_count'])
            ->setStudentCount((int) $data['student_count'])
            ->setEmail($email);
        $entityManager->persist($res);
        $entityManager->flush();
        out(201, ['data' => resArr($res)]);
    }

    if ($method === 'PUT') {
        $res = $repo->find((int) $id);
        if (!$res instanceof Reservations) out(404, ['error' => 'Réservation introuvable.']);
        $data = input();

        $dayToCheck = $res->getDay();
        $hourToCheck = $res->getHour();
        $newAdultCount = $res->getAdultCount();
        $newChildCount = $res->getChildCount();
        $newStudentCount = $res->getStudentCount();

        if (isset($data['day'])) {
            $dayStr = trim((string) $data['day']);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayStr)) out(422, ['error' => 'day doit être au format YYYY-MM-DD.']);
            $dayToCheck = $dayStr;
            $res->setDay($dayStr);
        }
        if (isset($data['hour'])) {
            $hourToCheck = trim((string) $data['hour']);
            $res->setHour($hourToCheck);
        }
        if (isset($data['price']))           $res->setPrice(trim((string) $data['price']));
        if (isset($data['adult_count'])) {
            if (!is_numeric($data['adult_count']) || (int) $data['adult_count'] < 0)
                out(422, ['error' => 'adult_count doit être un entier positif ou nul.']);
            $newAdultCount = (int) $data['adult_count'];
            $res->setAdultCount($newAdultCount);
        }
        if (isset($data['child_count'])) {
            if (!is_numeric($data['child_count']) || (int) $data['child_count'] < 0)
                out(422, ['error' => 'child_count doit être un entier positif ou nul.']);
            $newChildCount = (int) $data['child_count'];
            $res->setChildCount($newChildCount);
        }
        if (isset($data['student_count'])) {
            if (!is_numeric($data['student_count']) || (int) $data['student_count'] < 0)
                out(422, ['error' => 'student_count doit être un entier positif ou nul.']);
            $newStudentCount = (int) $data['student_count'];
            $res->setStudentCount($newStudentCount);
        }
        if (isset($data['email']) || isset($data['user_id'])) {
            $userEmail = strtolower(trim((string) ($data['email'] ?? $data['user_id'])));
            if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) out(422, ['error' => 'email invalide.']);
            $res->setEmail($userEmail);
        }

        $newPeopleCount = $newAdultCount + $newChildCount + $newStudentCount;
        $occupiedSlots = countSlotsForDayAndHour($entityManager, $dayToCheck, $hourToCheck, (int) $id);

        if ($occupiedSlots + $newPeopleCount > 10) {
            $availableSlots = max(0, 10 - $occupiedSlots);
            out(422, ['error' => "Créneau plein. Seulement $availableSlots place(s) restante(s) pour ce créneau le $dayToCheck à $hourToCheck."]);
        }

        $entityManager->flush();
        out(200, ['data' => resArr($res)]);
    }

    if ($method === 'DELETE') {
        $res = $repo->find((int) $id);
        if (!$res instanceof Reservations) out(404, ['error' => 'Réservation introuvable.']);
        $entityManager->remove($res);
        $entityManager->flush();
        out(200, ['message' => 'Réservation supprimée.']);
    }

    out(405, ['error' => 'Méthode non autorisée.']);
}

// send-email

if ($resource === 'send-email') {
    if ($method !== 'POST') {
        out(405, ['error' => 'Méthode non autorisée.']);
    }

    $data = input();
    
    // Validation des données requises
    $requiredFields = ['email', 'type', 'reservation_id', 'date', 'time', 'price'];
    foreach ($requiredFields as $field) {
        if (!array_key_exists($field, $data) || $data[$field] === '') {
            out(422, ['error' => "Champ $field requis."]);
        }
    }

    $recipientEmail = strtolower(trim((string) $data['email']));
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        out(422, ['error' => 'email invalide.']);
    }

    $emailType = trim((string) $data['type']);
    $reservationId = trim((string) $data['reservation_id']);
    $date = trim((string) $data['date']);
    $time = trim((string) $data['time']);
    $price = trim((string) $data['price']);

    // Construction de l'email
    if ($emailType === 'reservation_confirmation') {
        $subject = 'Confirmation de votre réservation - Kheti';
        
        $dateObj = DateTime::createFromFormat('Y-m-d', $date);
        $dateFormatted = $dateObj ? $dateObj->format('d/m/Y') : $date;
        
        $body = "
<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <style>
        body { font-family: Arial, sans-serif; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; }
        .header { background: #B69F5E; color: white; padding: 20px; text-align: center; }
        .content { background: white; padding: 20px; margin-top: 20px; }
        .detail { margin: 10px 0; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class=\"container\">
        <div class=\"header\">
            <h1>Kheti - Réservation Confirmée</h1>
        </div>
        <div class=\"content\">
            <p>Bonjour,</p>
            <p>Votre réservation a été confirmée avec succès ! Voici les détails de votre visite :</p>
            
            <div class=\"detail\">
                <strong>Numéro de réservation :</strong> #" . htmlspecialchars($reservationId) . "
            </div>
            <div class=\"detail\">
                <strong>Date :</strong> " . htmlspecialchars($dateFormatted) . "
            </div>
            <div class=\"detail\">
                <strong>Heure :</strong> " . htmlspecialchars($time) . "
            </div>
            <div class=\"detail\">
                <strong>Montant :</strong> " . htmlspecialchars($price) . " €
            </div>
            
            <p>Merci de votre réservation. À bientôt dans les mystères de l'Égypte !</p>
            
            <div class=\"footer\">
                <p>Cet email a été envoyé automatiquement. Veuillez ne pas répondre directement à ce message.</p>
                <p>Pour toute question, contactez-nous via notre site : www.kheti.com</p>
            </div>
        </div>
    </div>
</body>
</html>
        ";
    } else {
        out(422, ['error' => 'Type d\'email non supporté.']);
    }

    // Préparation des headers
    $senderEmail = 'faravision.agency@gmail.com';
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . $senderEmail . "\r\n";
    $headers .= "X-Mailer: Kheti Reservation System\r\n";

    // Tentative d'envoi
    $mailSent = @mail($recipientEmail, $subject, $body, $headers);

    if ($mailSent) {
        out(200, ['success' => true, 'message' => 'Email envoyé avec succès.']);
    }

    if (isLocalRequest()) {
        out(202, [
            'success' => true,
            'message' => 'Envoi d\'email non disponible en local (WAMP sans SMTP).',
            'simulated' => true,
        ]);
    }

    out(500, ['error' => 'L\'email n\'a pas pu être envoyé. Contactez l\'administrateur.']);
}

out(405, ['error' => 'Méthode non autorisée.']);