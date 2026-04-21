# Bundles, Librairies & APIs — BizHub
**Projet :** Gestion Investissement — ESPRIT PIDEV 3A 2025/2026
**Framework :** Symfony 6.4 · PHP 8.2

---

## 1. Bundles Symfony

| Nom | Version | Lien | Rôle |
|-----|---------|------|------|
| **FrameworkBundle** | 6.4.* | https://github.com/symfony/framework-bundle | Noyau Symfony : routing, controllers, services, configuration |
| **SecurityBundle** | 6.4.* | https://github.com/symfony/security-bundle | Authentification, rôles, firewall, Google OAuth |
| **TwigBundle** | 6.4.* | https://github.com/symfony/twig-bundle | Moteur de templates Twig intégré à Symfony |
| **DoctrineBundle** | ^2.18 | https://github.com/doctrine/DoctrineBundle | Intégration Doctrine ORM dans Symfony |
| **DoctrineMigrationsBundle** | ^3.7 | https://github.com/doctrine/DoctrineMigrationsBundle | Gestion des migrations de base de données |
| **MonologBundle** | ^3.0 | https://github.com/symfony/monolog-bundle | Logging applicatif via Monolog |
| **TbbcMoneyBundle** | ^7.1 | https://github.com/TheBigBrainsCompany/TbbcMoneyBundle | Intègre moneyphp dans Symfony : filtres Twig, FormTypes, gestion multi-devises |
| **VichUploaderBundle** | ^2.9 | https://github.com/dustin10/VichUploaderBundle | Upload de fichiers / images géré automatiquement via Doctrine (module Marketplace) |
| **SchebTwoFactorBundle** | ^6.0 | https://github.com/scheb/two-factor-bundle | Authentification à deux facteurs (2FA / TOTP) |
| **SymfonyCasts VerifyEmailBundle** | ^1.17 | https://github.com/SymfonyCasts/verify-email-bundle | Vérification d'adresse email à l'inscription |
| **StimulusBundle** | ^2.0 | https://github.com/symfony/stimulus-bundle | Intégration Stimulus JS (interactions frontend légères) |
| **UX Turbo** | ^2.0 | https://github.com/symfony/ux-turbo | Navigation rapide sans rechargement de page (Turbo Drive) |
| **TwigExtraBundle** | ^3.0 | https://github.com/twigphp/Twig | Extensions Twig supplémentaires (filtres avancés) |
| **MakerBundle** | ^1.0 | https://github.com/symfony/maker-bundle | Génération de code (dev uniquement) |
| **WebProfilerBundle** | 6.4.* | https://github.com/symfony/web-profiler-bundle | Barre de débogage Symfony (dev uniquement) |

---

## 2. Librairies PHP

| Nom | Version | Lien | Rôle |
|-----|---------|------|------|
| **moneyphp/money** | ^4.8 | https://github.com/moneyphp/money | Manipulation précise des montants monétaires (évite les erreurs de float) |
| **dompdf/dompdf** | ^3.1 | https://github.com/dompdf/dompdf | Génération de fichiers PDF depuis du HTML (contrats, fiches projet) |
| **stripe/stripe-php** | ^20.0 | https://github.com/stripe/stripe-php | SDK officiel Stripe pour les paiements en ligne |
| **twilio/sdk** | ^8.11 | https://github.com/twilio/twilio-php | Envoi de SMS de confirmation (module Marketplace) |
| **bacon/bacon-qr-code** | ^2.0 | https://github.com/Bacon/BaconQrCode | Génération de QR codes (authentification 2FA) |
| **intervention/image** | ^3.0 | https://github.com/Intervention/image | Traitement et redimensionnement d'images |
| **doctrine/orm** | ^3.6 | https://github.com/doctrine/orm | ORM pour la gestion de la base de données |

---

## 3. APIs Externes

| Nom | Type | Lien | Rôle |
|-----|------|------|------|
| **Stripe API** | REST API | https://stripe.com/docs/api | Paiement sécurisé en ligne (module Deal — étape paiement du workflow) |
| **Yousign API** | REST API | https://developers.yousign.com | Signature électronique des contrats d'investissement |
| **OpenAI API** | REST API | https://platform.openai.com/docs | Intelligence artificielle : analyse de négociation, coaching projet (GPT-4o) |
| **Groq API** | REST API | https://console.groq.com/docs | IA rapide compatible OpenAI : analyse scoring Marketplace (LLaMA 3) |
| **CoinGecko API** | REST API | https://www.coingecko.com/en/api | Prix en temps réel des cryptomonnaies (BTC, ETH, USDT, BNB) |
| **Exchange Rate API** | REST API | https://www.exchangerate-api.com | Taux de change en temps réel (EUR→TND, EUR→USD, USD→TND) |

---

## 4. Symfony Core (composants principaux utilisés)

| Composant | Rôle |
|-----------|------|
| Security Bundle | Authentification, rôles, firewall, Google OAuth |
| Form | Formulaires typés et validés |
| Validator | Contraintes de validation sur les entités |
| Mailer | Envoi d'emails (confirmation, signature) |
| HttpClient | Appels vers les APIs externes |
| Twig | Moteur de templates HTML |
| Translation | Internationalisation des messages |

---

## Note

> **DoctrineEncryptBundle** n'est pas installé dans ce projet.
> Le chiffrement des données sensibles est géré au niveau applicatif si nécessaire.

---

## Phrase de présentation

> *« Dans ce projet, j'ai utilisé des bundles et librairies éprouvées pour chaque besoin métier : moneyphp pour la précision des calculs financiers, Dompdf pour la génération de PDF, VichUploaderBundle pour la gestion des images produits, Stripe et Yousign pour le workflow de paiement et de signature, et des APIs externes comme CoinGecko pour les données de marché en temps réel. Chaque outil a été choisi pour sa fiabilité, sa compatibilité avec Symfony 6.4 et sa valeur ajoutée dans le contexte d'une plateforme d'investissement professionnelle. »*
