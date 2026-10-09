# Documentation Technique — PHARMACIE BLESSING

---

## 1. Langages et Technologies Utilisées

| Catégorie | Technologie | Rôle |
|---|---|---|
| **Backend** | PHP 8.x | Logique serveur, traitement des formulaires, sessions |
| **Base de données** | MySQL (via XAMPP) | Stockage et gestion des données |
| **Frontend** | HTML5 | Structure des pages |
| **Frontend** | CSS3 | Styles personnalisés (`assets/css/style.css`) |
| **Frontend** | JavaScript (ES6+) | Animations, filtres en temps réel, AJAX |
| **Framework CSS** | Bootstrap 5.3 | Grilles, composants responsive, modals |
| **Icônes** | Font Awesome 6.5 | Icônes dans l'interface |
| **Polices** | Google Fonts (Inter, Outfit) | Typographie moderne |
| **Connexion BDD** | PDO (PHP Data Objects) | Interface sécurisée avec MySQL |
| **Serveur local** | XAMPP (Apache + MySQL) | Hébergement local du projet |

---

## 2. Base de Données

- **Nom de la base :** `pharmacie_blessing`
- **Système :** MySQL
- **Hôte :** 127.0.0.1 (localhost)
- **Encodage :** UTF-8 MB4
- **Connexion :** PDO avec mode exception activé

---

## 3. Tables et Leurs Descriptions

### 🔵 `users` — Utilisateurs du système
Stocke les comptes qui ont accès au tableau de bord.

| Colonne | Type | Description |
|---|---|---|
| `id` | INT (PK) | Identifiant unique |
| `full_name` | VARCHAR | Nom complet de l'utilisateur |
| `username` | VARCHAR | Nom d'utilisateur (login) |
| `password` | VARCHAR | Mot de passe hashé |
| `role_id` | INT (FK) | Référence vers `roles` |
| `status` | ENUM | Actif / Inactif |
| `created_at` | DATETIME | Date de création |

**Rôles existants :** `Super Admin`, `Admin`, `Gérant`, `Caissier`, `Facturier`, `Livreur`

---

### 🟢 `categories` — Catégories de médicaments

| Colonne | Type | Description |
|---|---|---|
| `id` | INT (PK) | Identifiant unique |
| `name` | VARCHAR | Nom de la catégorie |
| `description` | TEXT | Description optionnelle |
| `created_at` | DATETIME | Date de création |

---

### 🟢 `products` — Médicaments / Produits

| Colonne | Type | Description |
|---|---|---|
| `id` | INT (PK) | Identifiant unique |
| `code` | VARCHAR | Code barre / référence unique |
| `name` | VARCHAR | Nom du produit |
| `description` | TEXT | Description |
| `category_id` | INT (FK) | Référence vers `categories` |
| `brand` | VARCHAR | Marque / laboratoire |
| `buy_price` | DECIMAL | Prix d'achat (PA) |
| `sell_price` | DECIMAL | Prix de vente (PV) |
| `qty` | INT | Quantité en stock |
| `alert_threshold` | INT | Seuil d'alerte de stock |
| `image` | VARCHAR | Nom du fichier image |
| `mfg_date` | DATE | Date de fabrication |
| `exp_date` | DATE | Date d'expiration |
| `created_at` | DATETIME | Date d'ajout |

---

### 🔵 `clients` — Clients de la pharmacie

| Colonne | Type | Description |
|---|---|---|
| `id` | INT (PK) | Identifiant unique |
| `name` | VARCHAR | Nom du client |
| `phone` | VARCHAR | Téléphone |
| `email` | VARCHAR | Email |
| `address` | VARCHAR | Adresse |
| `photo` | VARCHAR | Photo de profil |
| `created_at` | DATETIME | Date d'enregistrement |

---

### 🔵 `suppliers` — Fournisseurs

| Colonne | Type | Description |
|---|---|---|
| `id` | INT (PK) | Identifiant unique |
| `name` | VARCHAR | Nom du fournisseur |
| `phone` | VARCHAR | Téléphone |
| `email` | VARCHAR | Email |
| `address` | VARCHAR | Adresse |
| `created_at` | DATETIME | Date d'enregistrement |

---

### 🟡 `sales` — Transactions de vente

| Colonne | Type | Description |
|---|---|---|
| `id` | INT (PK) | Identifiant unique |
| `client_id` | INT (FK) | Référence vers `clients` (nullable) |
| `user_id` | INT (FK) | Caissier — référence vers `users` |
| `total_amount` | DECIMAL | Montant après remise |
| `discount` | DECIMAL | Montant de la remise |
| `final_amount` | DECIMAL | Montant final payé |
| `sale_date` | DATETIME | Date et heure de la vente |

---

### 🟡 `sale_details` — Détails des articles vendus

| Colonne | Type | Description |
|---|---|---|
| `id` | INT (PK) | Identifiant unique |
| `sale_id` | INT (FK) | Référence vers `sales` |
| `product_id` | INT (FK) | Référence vers `products` |
| `qty` | INT | Quantité vendue |
| `price` | DECIMAL | Prix unitaire au moment de la vente |

---

### 🟠 `purchases` — Achats fournisseurs (entrées en stock)

| Colonne | Type | Description |
|---|---|---|
| `id` | INT (PK) | Identifiant unique |
| `supplier_id` | INT (FK) | Référence vers `suppliers` |
| `total_amount` | DECIMAL | Montant total de l'achat |
| `status` | VARCHAR | Statut (ex: reçu, en attente) |
| `created_by` | INT (FK) | Utilisateur — référence vers `users` |
| `purchase_date` | DATETIME | Date de l'achat |

---

### 🟠 `purchase_details` — Détails des articles achetés

| Colonne | Type | Description |
|---|---|---|
| `id` | INT (PK) | Identifiant unique |
| `purchase_id` | INT (FK) | Référence vers `purchases` |
| `product_id` | INT (FK) | Référence vers `products` |
| `qty` | INT | Quantité reçue |
| `buy_price` | DECIMAL | Prix d'achat unitaire |

---

### 🔴 `stock_movements` — Journal des mouvements de stock

| Colonne | Type | Description |
|---|---|---|
| `id` | INT (PK) | Identifiant unique |
| `product_id` | INT (FK) | Référence vers `products` |
| `type` | ENUM | `IN` (entrée) ou `OUT` (sortie) |
| `qty` | INT | Quantité déplacée |
| `user_id` | INT (FK) | Utilisateur responsable |
| `reference_id` | INT | ID de la vente ou achat lié |
| `notes` | VARCHAR | Commentaire |
| `created_at` | DATETIME | Date du mouvement |

---

### 🔴 `audit_logs` — Journal d'audit des actions

| Colonne | Type | Description |
|---|---|---|
| `id` | INT (PK) | Identifiant unique |
| `user_id` | INT (FK) | Référence vers `users` |
| `action` | VARCHAR | Action effectuée |
| `details` | TEXT | Détails supplémentaires |
| `created_at` | DATETIME | Date de l'action |

---

### 🟣 `notifications` — Notifications système

| Colonne | Type | Description |
|---|---|---|
| `id` | INT (PK) | Identifiant unique |
| `message` | VARCHAR | Contenu de la notification |
| `status` | ENUM | `unread` / `read` |
| `created_at` | DATETIME | Date de création |

---

## 4. Diagramme des Relations (ERD)

```
users ──────────────────────────────────┐
  │                                      │
  ├──[user_id]──► sales ◄──[client_id]── clients
  │                  │
  │                  └──[sale_id]──► sale_details ◄──[product_id]── products
  │                                                                      │
  ├──[user_id]──► purchases ◄──[supplier_id]── suppliers                 │
  │                   │                                                   │
  │                   └──[purchase_id]──► purchase_details ◄──[product_id]┘
  │
  ├──[user_id]──► stock_movements ◄──[product_id]──────────── products
  │
  └──[user_id]──► audit_logs
```

**Résumé des clés étrangères (FK) :**

| Table | Colonne FK | Référence vers |
|---|---|---|
| `users` | `role_id` | `roles.id` |
| `products` | `category_id` | `categories.id` |
| `sales` | `client_id` | `clients.id` |
| `sales` | `user_id` | `users.id` |
| `sale_details` | `sale_id` | `sales.id` |
| `sale_details` | `product_id` | `products.id` |
| `purchases` | `supplier_id` | `suppliers.id` |
| `purchases` | `created_by` | `users.id` |
| `purchase_details` | `purchase_id` | `purchases.id` |
| `purchase_details` | `product_id` | `products.id` |
| `stock_movements` | `product_id` | `products.id` |
| `stock_movements` | `user_id` | `users.id` |
| `audit_logs` | `user_id` | `users.id` |

---

## 5. Structure du Projet

```
PHARMACIE BLESSING/
├── index.php                  # Point d'entrée (redirection)
├── login.php                  # Page de connexion
├── logout.php                 # Déconnexion
├── dashboard.php              # Tableau de bord admin
├── public_home.php            # Page d'accueil publique
│
├── config/
│   └── db.php                 # Connexion PDO à MySQL
│
├── includes/
│   ├── functions.php          # Fonctions globales (auth, log, format)
│   ├── header.php             # En-tête HTML + navbar admin
│   ├── sidebar.php            # Menu latéral
│   └── footer.php             # Pied de page + JS toggle sidebar
│
├── assets/
│   ├── css/style.css          # Styles personnalisés + responsive
│   └── img/                   # Images (logo pha.jpeg, a.jpeg, b.jpeg, c.jpeg)
│
└── modules/
    ├── sales/                 # Ventes (POS, liste, facture)
    ├── purchases/             # Achats fournisseurs (liste, bon d'entrée)
    ├── products/              # Gestion médicaments
    ├── categories/            # Catégories
    ├── clients/               # Gestion clients
    ├── suppliers/             # Gestion fournisseurs
    ├── stock/                 # Mouvements de stock (IN / OUT)
    ├── caisse/                # Module caisse du jour
    ├── reports/               # Rapports et statistiques
    ├── users/                 # Gestion utilisateurs
    ├── audit/                 # Journal d'audit
    └── settings/              # Paramètres système
```

---

*Document généré automatiquement — Pharmacie Blessing © 2026*
