# API Kheti (Back-end)

API REST en PHP natif + Doctrine ORM pour la gestion des utilisateurs et des reservations.

## Prerequis

- PHP 8.1+
- MySQL
- Composer
- WAMP/XAMPP (ou serveur Apache/Nginx equivalent)

## Installation

1. Installer les dependances:

```bash
composer install
```

2. Verifier la connexion DB dans bootstrap.php:

- dbname
- user
- password
- host

3. Placer le projet dans le dossier web.

4. Demarrer Apache + MySQL.

## Point d'entree API

Le point d'entree est:

- `/api/index.php`

Exemple local:

- `http://localhost/kheti/back-end/api/index.php`

## Format des reponses

- Succes: `{ "data": ... }` ou `{ "message": ... }`
- Erreur: `{ "error": "..." }`

Codes HTTP utilises:

- 200 OK
- 201 Created
- 204 No Content (OPTIONS)
- 400 Bad Request
- 401 Unauthorized
- 404 Not Found
- 405 Method Not Allowed
- 409 Conflict
- 422 Unprocessable Entity

## Authentification

### Login

- Methode: `POST`
- URL: `/api/index.php/auth`
- Body JSON:

```json
{
  "email": "admin@example.com",
  "password": "motdepasse"
}
```

- Reponse:

```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "Nom",
      "first_name": "Prenom",
      "email": "admin@example.com",
      "admin_state": 1
    },
    "token": "jwt_token"
  }
}
```

## Endpoints

### Racine API

- `GET /api/index.php`
- Retourne la liste des endpoints principaux.

### Users

- `GET /api/index.php/users`
  - Liste des utilisateurs
- `GET /api/index.php/users/{email}`
  - Detail d'un utilisateur (id dans l'URL = email)
- `POST /api/index.php/users`
  - Creer un utilisateur
- `PUT /api/index.php/users/{email}`
  - Modifier un utilisateur
- `DELETE /api/index.php/users/{email}`
  - Supprimer un utilisateur

Body attendu pour `POST /users`:

```json
{
  "name": "Doe",
  "first_name": "John",
  "email": "john@example.com",
  "password": "secret",
  "admin_state": 0
}
```

### Reservations

- `GET /api/index.php/reservations`
  - Liste des reservations
- `GET /api/index.php/reservations/{id}`
  - Detail reservation
- `POST /api/index.php/reservations`
  - Creer reservation
- `PUT /api/index.php/reservations/{id}`
  - Modifier reservation
- `DELETE /api/index.php/reservations/{id}`
  - Supprimer reservation

Body attendu pour `POST /reservations`:

```json
{
  "day": "2026-03-28",
  "hour": "14:00",
  "price": "45.00",
  "adult_count": 2,
  "child_count": 1,
  "student_count": 0,
  "email": "john@example.com"
}
```

Regle metier actuelle:

- Capacite max de 10 personnes par couple `(day, hour)`.

## Exemples cURL

### Login

```bash
curl -X POST "http://localhost/kheti/back-end/api/index.php/auth" \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"secret"}'
```

### Creer un utilisateur

```bash
curl -X POST "http://localhost/kheti/back-end/api/index.php/users" \
  -H "Content-Type: application/json" \
  -d '{"name":"Doe","first_name":"John","email":"john@example.com","password":"secret","admin_state":0}'
```

### Creer une reservation

```bash
curl -X POST "http://localhost/kheti/back-end/api/index.php/reservations" \
  -H "Content-Type: application/json" \
  -d '{"day":"2026-03-28","hour":"14:00","price":"45.00","adult_count":2,"child_count":1,"student_count":0,"email":"john@example.com"}'
```

## Depannage rapide

- `JSON invalide`:
  - Verifier le body JSON et le header `Content-Type: application/json`.
- `Identifiants invalides`:
  - Verifier email/mot de passe et hash en base.
- `Ressource inconnue`:
  - Verifier l'URL et le segment (`auth`, `users`, `reservations`).
- `Methode non autorisee`:
  - Verifier la methode HTTP envoyee.
- `Creaneau plein`:
  - La somme adultes + enfants + etudiants depasse la capacite du creneau.

## Structure projet

- `api/index.php`: routeur principal et logique API
- `bootstrap.php`: configuration Doctrine + connexion DB
- `src/Users.php`: entite utilisateurs
- `src/Reservations.php`: entite reservations
- `bin/doctrine`: CLI Doctrine
