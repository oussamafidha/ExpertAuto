# Le professionnel — Symfony 7 / PHP 8.2+ / MySQL

Application Web : tableau de bord, voitures, rendez-vous, réparations, mécaniciens/utilisateurs, stock de pièces et factures.

## Prérequis
- PHP 8.2+, extensions `pdo_mysql`, `ctype`, `iconv`, `mbstring`
- Composer 2
- MySQL 8
- Optionnel : Symfony CLI

## Installation
1. Décompresser le ZIP et ouvrir un terminal dans le dossier.
2. Créer un utilisateur et une base MySQL :
   ```sql
   CREATE DATABASE expert_auto_plus CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'amine'@'localhost' IDENTIFIED BY 'amine1987';
   GRANT ALL PRIVILEGES ON expert_auto_plus.* TO 'amine'@'localhost';
   FLUSH PRIVILEGES;
   ```
3. Adapter `DATABASE_URL` et `APP_SECRET` dans `.env`.
4. Installer :
   ```bash
   composer install
   ```
5. Créer les tables à partir des entités :
   ```bash
   php bin/console doctrine:schema:create
   ```
6. Lancer le serveur :
   ```bash
   symfony server:start
   ```
   ou en test :
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```
7. Ouvrir `http://127.0.0.1:8000/initialiser-admin`, créer le premier compte (mot de passe d'au moins 12 caractères), puis se connecter via `/connexion`.
8. **Sécurisation obligatoire :** supprimer ou désactiver la route `/initialiser-admin` après avoir créé le premier compte.

## Modules
- Tableau de bord : voitures du jour, réparations en cours, alertes de stock.
- Voitures : ajout, modification, recherche, client et téléphone.
- Rendez-vous : date/heure, intervention, statut, mécanicien affecté.
- Réparations : véhicule, mécanicien, description, statut, main-d'œuvre.
- Pièces : référence, stock, fournisseur, prix, seuil d'alerte.
- Factures : numéro, véhicule, réparation, main-d'œuvre, pièces, taux de taxe et total calculé.
- Connexion sécurisée par hachage du mot de passe et firewall Symfony.

## Rôles
L'utilisateur créé via la page d'initialisation reçoit `ROLE_ADMIN` et `ROLE_MECHANIC`. Le modèle supporte `ROLE_RECEPTION` et `ROLE_MECHANIC`, mais l'interface de gestion des comptes et la restriction fine par rôle sont à implémenter avant une mise en production.

## Limites connues de ce starter
Ce projet est une base fonctionnelle de départ, pas un logiciel de facturation certifié ni un produit prêt pour la production. Les formulaires de gestion sont volontairement simples et doivent être renforcés avec CSRF, validations serveur systématiques et contrôles de droits. La gestion de stock n'est pas encore décrémentée automatiquement lors de l'utilisation d'une pièce dans une réparation. Les lignes détaillées de facture, l'impression PDF, les paiements et la détection des conflits de rendez-vous sont des évolutions à ajouter. Faites une sauvegarde avant toute utilisation réelle.
