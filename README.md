# OCP Document Management Platform - Backend



![Laravel](https://img.shields.io/badge/Laravel-13.x-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Sanctum](https://img.shields.io/badge/Sanctum-4.x-2B2B2B?logo=laravel&logoColor=white)

Backend API pour la plateforme OCP Documents. Gère l’authentification, les rôles, les services, axes, dossiers, fichiers et l’historique (audit logs).

Ce dépôt contient **le backend** (Laravel 13). The frontend is available in a separate repository.

## Related Repository

- Frontend: https://github.com/Maelainine-ElKhalsi/ocp-document-management-frontend
---

## Demo

- API (par défaut): `http://localhost:8000`
- Base de données: MySQL (par défaut)

---

## Pourquoi ce projet ?

Ce backend fournit une API REST pour:

- Authentification des utilisateurs (JWT via Sanctum)
- Gestion des services, axes, dossiers et fichiers
- Contrôle d’accès par rôles (Admin / Chef / Technicien)
- Historique des actions (audit logs) pour traçabilité

---

## Stack technique

- **Laravel 13** (framework PHP)
- **PHP 8.3**
- **Laravel Sanctum** (authentification API)
- **MySQL** (base de données par défaut, configurable pour SQLite/PostgreSQL)
- **Eloquent ORM**

---

## Prérequis

- PHP 8.3+
- Composer
- Node.js & npm (pour les assets frontend si inclus)

---

## Installation & démarrage

### 1) Cloner le projet

```bash
git clone <url-du-repo>
cd ocp-backend
```

### 2) Installer les dépendances

```bash
composer install
```

### 3) Configurer l’environnement

```bash
cp .env.example .env
php artisan key:generate
```

### 4) Configurer la base de données

Par défaut, le projet utilise MySQL. Assure-toi que ton serveur MySQL est en cours d’exécution et configure `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ocp_documents
DB_USERNAME=root
DB_PASSWORD=
```

Si tu veux utiliser SQLite à la place, modifie `.env`:

```env
DB_CONNECTION=sqlite
```

Et crée le fichier:

```bash
touch database/database.sqlite
```

### 5) Exécuter les migrations

```bash
php artisan migrate
```

### 6) Lancer le serveur

```bash
php artisan serve
```

L’API sera accessible sur `http://localhost:8000`.

---

## API Endpoints

### Authentification

- `POST /api/register` — Inscription
- `POST /api/login` — Connexion (retourne token)
- `POST /api/logout` — Déconnexion (protégé)
- `GET /api/me` — Utilisateur connecté (protégé)

### Services

- `GET /api/services` — Liste des services
- `POST /api/services` — Créer un service
- `GET /api/services/{id}` — Détail d’un service
- `PUT /api/services/{id}` — Modifier un service
- `DELETE /api/services/{id}` — Supprimer un service

### Axes

- `GET /api/axes` — Liste des axes
- `POST /api/axes` — Créer un axe
- `GET /api/axes/{id}` — Détail d’un axe
- `PUT /api/axes/{id}` — Modifier un axe
- `DELETE /api/axes/{id}` — Supprimer un axe

### Dossiers

- `GET /api/dossiers` — Liste des dossiers
- `POST /api/dossiers` — Créer un dossier
- `GET /api/dossiers/{id}` — Détail d’un dossier
- `PUT /api/dossiers/{id}` — Modifier un dossier
- `DELETE /api/dossiers/{id}` — Supprimer un dossier

### Fichiers

- `GET /api/files` — Liste des fichiers
- `POST /api/files` — Uploader un fichier
- `GET /api/files/{id}` — Détail d’un fichier
- `PUT /api/files/{id}` — Modifier un fichier
- `DELETE /api/files/{id}` — Supprimer un fichier
- `GET /api/files/{id}/view` — Visualiser un fichier
- `GET /api/files/{id}/download` — Télécharger un fichier

### Audit Logs (Historique)

- `GET /api/audit-logs/latest` — Dernières actions globales (limit param)
- `GET /api/audit-logs/users/{id}` — Dernières actions d’un utilisateur (limit param)

### Utilisateurs (Admin only)

- `GET /api/users` — Liste des utilisateurs
- `POST /api/users` — Créer un utilisateur
- `PUT /api/users/{id}` — Modifier un utilisateur
- `DELETE /api/users/{id}` — Supprimer un utilisateur
- `GET /api/users/search?q=...` — Recherche d’utilisateurs (autocomplete)

---

## Authentification

- Utilisation de **Laravel Sanctum** pour les tokens API.
- Le frontend doit envoyer le token dans le header `Authorization: Bearer {token}`.
- Les routes protégées utilisent le middleware `auth:sanctum`.

---

## Structure du projet

```
app/
  Http/
    Controllers/
      Api/
        AuthController.php
        ServiceController.php
        AxeController.php
        DossierController.php
        FileController.php
        AuditLogController.php
        UserController.php
  Models/
    User.php
    Service.php
    Axe.php
    Dossier.php
    File.php
    AuditLog.php
database/
  migrations/
routes/
  api.php
```

---

## Configuration

### CORS



```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => ['http://localhost:5173'],
```

### Base de données

Par défaut: MySQL.

Configure `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ocp_documents
DB_USERNAME=root
DB_PASSWORD=
```

Pour SQLite à la place:

```env
DB_CONNECTION=sqlite
```

Et crée le fichier:

```bash
touch database/database.sqlite
```

---

## Scripts utiles

```bash
composer run setup           # Installation complète + migrations
composer run dev            # Serveur + queue + logs + Vite
composer run test           # Tests PHPUnit
php artisan migrate:fresh   # Reset base de données
php artisan tinker          # Console interactive
```

---

## Notes / limites

- Par défaut, la base de données est MySQL. Pour le développement, tu peux utiliser SQLite.
- Assure-toi que CORS est configuré correctement pour le frontend.
- Les permissions et rôles sont gérés côté backend (vérifie les policies/middleware si ajoutés).

---

## Contribution

Si tu veux contribuer:

- Créer une branche (feature)
- Faire des commits clairs
- Ouvrir une Pull Request

---

## Roadmap (idées)

- Ajouter des tests unitaires et d’intégration
- Implémenter des policies pour les rôles (Admin/Chef/Technicien)
- Ajouter pagination sur les endpoints list
- Export de logs (CSV/Excel)

---

## Licence

Ce projet est distribué sous la licence MIT. Voir le fichier `LICENSE` pour plus d'informations.

## Author

**Maelainine El Khalsi**

- GitHub: https://github.com/Maelainine-ElKhalsi
- inkedin.com/in/maelainine-el-khalsi-731a293bb
