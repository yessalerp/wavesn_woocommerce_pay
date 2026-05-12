# Wave Sénégal — Passerelle de Paiement WooCommerce

> Développé par **[Jaxaay Group](https://jaxaaygroup.com)** · Dakar, Sénégal

[![Version](https://img.shields.io/badge/version-2.0.0-blue)](https://github.com/yessalerp/wavesn_woocommerce_pay)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-6.0%2B-purple)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://php.net)
[![License](https://img.shields.io/badge/license-GPL--2.0-green)](LICENSE)

Plugin WordPress/WooCommerce officiel pour intégrer **Wave Mobile Money** comme passerelle de paiement en XOF (Franc CFA).

---

## 👤 Développeur

| | |
|---|---|
| **Société** | Jaxaay Group |
| **Adresse** | Parcelles Assainies U22, Villa N°529 — Dakar, Sénégal |
| **Email** | [jaxaay@jaxaaygroup.com](mailto:jaxaay@jaxaaygroup.com) |
| **Site web** | [jaxaaygroup.com](https://jaxaaygroup.com) |
| **Téléphone** | [+221 78 651 15 15](tel:+221786511515) |
| **GitHub** | [yessalerp/wavesn_woocommerce_pay](https://github.com/yessalerp/wavesn_woocommerce_pay) |

---

## 📁 Structure du Plugin

```
woocommerce-wave-senegal/
├── woocommerce-wave-senegal.php        ← Fichier principal (headers, constantes, hooks)
├── includes/
│   ├── class-wc-wave-sn-api.php       ← Client API Wave Checkout
│   ├── class-wc-wave-sn-gateway.php   ← Passerelle WooCommerce
│   ├── class-wc-wave-sn-webhook.php   ← Réception & traitement webhooks
│   └── class-wc-wave-sn-logger.php    ← Système de logs
├── assets/
│   ├── css/
│   │   ├── wave-frontend.css           ← Styles checkout / thank you page
│   │   └── wave-admin.css              ← Styles page de configuration
│   ├── js/
│   │   ├── wave-frontend.js            ← Polling statut paiement
│   │   └── wave-admin.js               ← Test API, copie URL webhook
│   └── images/
│       ├── wave-logo.png               ← Logo original
│       ├── wave-logo-checkout.png      ← Logo checkout (60×60)
│       ├── wave-icon-32.png            ← Icône passerelle (32×32)
│       ├── wave-icon-20.png            ← Icône admin (20×20)
│       ├── wave-icon-40.png            ← Icône Retina (40×40)
│       └── wave-banner.png             ← Bannière admin (772×250)
└── languages/                          ← Fichiers de traduction (.po / .mo)
```

---

## ✨ Fonctionnalités

| Fonctionnalité | Statut |
|---|---|
| Création de session de paiement Wave | ✅ |
| Redirection vers l'app Wave | ✅ |
| Webhooks temps réel | ✅ |
| Polling statut (page confirmation) | ✅ |
| Remboursements via API Wave | ✅ |
| Signature HMAC-SHA256 des requêtes | ✅ |
| Vérification signature webhook | ✅ |
| Logs détaillés (mode debug) | ✅ |
| Table de transactions BDD | ✅ |
| Test de la clé API en admin | ✅ |
| Compatibilité HPOS WooCommerce | ✅ |
| Multilingue (i18n) | ✅ |

---

## 🚀 Installation

### Via WordPress (recommandé)
1. Téléchargez le fichier `woocommerce-wave-senegal.zip`
2. **Extensions > Ajouter > Téléverser une extension**
3. Activez le plugin

### Via FTP
```bash
# Copier le dossier dans wp-content/plugins/
scp -r woocommerce-wave-senegal/ user@serveur:/var/www/html/wp-content/plugins/
```

### Via GitHub
```bash
cd /var/www/html/wp-content/plugins/
git clone https://github.com/yessalerp/wavesn_woocommerce_pay.git woocommerce-wave-senegal
```

---

## ⚙️ Configuration

### 1. Clés API Wave
Connectez-vous au [Portail Business Wave](https://business.wave.com/dev-portal) :
- Section **Développeur > Clés API**
- **Créer une nouvelle clé API**
- *(Optionnel mais recommandé)* Activer la **signature des requêtes**
- Copier la clé API et le secret de signature (visibles **une seule fois**)

### 2. Paramètres WooCommerce
`WooCommerce > Paramètres > Paiements > Wave Sénégal`

| Champ | Description |
|---|---|
| Clé API | `wave_sn_prod_...` |
| Secret de signature | `wave_sn_AKS_...` (HMAC-SHA256) |
| URL Webhook | Copiez et collez dans le portail Wave |
| Secret Webhook | Pour vérifier les webhooks entrants |

### 3. Configurer le Webhook
Copiez l'URL affichée dans les paramètres du plugin :
```
https://votre-site.com/?wc-api=wave_senegal_webhook
```
Et configurez-la dans votre portail Business Wave.

---

## 🔄 Flux de Paiement

```
Client sélectionne "Wave Mobile Money"
            ↓
process_payment() → POST /v1/checkout/sessions
            ↓
Redirection vers wave_launch_url (app Wave)
            ↓
         Client paie
        ↙           ↘
   Webhook          Retour URL
POST /webhook    GET success_url
     ↓                ↓
Mise à jour      Vérification
  commande       GET /v1/checkout/sessions/:id
```

---

## 📡 API Wave utilisée

Base URL : `https://api.wave.com`

| Méthode | Endpoint | Usage |
|---|---|---|
| `POST` | `/v1/checkout/sessions` | Créer une session |
| `GET` | `/v1/checkout/sessions/:id` | Vérifier une session |
| `GET` | `/v1/checkout/sessions/search` | Rechercher par référence |
| `POST` | `/v1/checkout/sessions/:id/refund` | Rembourser |
| `POST` | `/v1/checkout/sessions/:id/expire` | Expirer |

---

## 🗃️ Métadonnées des Commandes

| Meta Key | Description |
|---|---|
| `_wave_checkout_session_id` | ID session Wave (`cos-...`) |
| `_wave_transaction_id` | ID transaction Wave |
| `_wave_client_reference` | Référence unique `wc_order_{id}_{hash}` |
| `_wave_launch_url` | URL de paiement Wave |
| `_wave_amount` | Montant en XOF |
| `_wave_payment_status` | `processing` / `succeeded` / `cancelled` |
| `_wave_checkout_status` | `open` / `complete` / `expired` |

---

## ⚠️ Prérequis

- WordPress **5.8+**
- WooCommerce **6.0+**
- PHP **7.4+**
- Devise WooCommerce : **XOF** (Franc CFA)
- **HTTPS** (obligatoire en production)
- Compte **Wave Business** avec accès API

---

## 🔒 Sécurité

- Clés API jamais exposées côté client
- Signature **HMAC-SHA256** des requêtes (optionnel)
- Vérification signature des webhooks
- Validation du timestamp (tolérance ±5 minutes)
- Toutes les communications via **HTTPS**
- Escape/sanitize sur toutes les entrées/sorties

---

## 📝 Changelog

### v2.0.0 (2024)
- Réécriture complète PHP 7.4+ avec types stricts
- Branding & intégration **Jaxaay Group**
- Logo Wave intégré (checkout + admin)
- Bannière admin personnalisée
- Compatibilité **HPOS** WooCommerce
- Signature HMAC-SHA256
- Table de transactions MySQL
- Polling statut côté client (every 5s)
- Test connexion API depuis l'admin
- Copie URL webhook en un clic
- Liens support dans l'interface admin

---

## 📞 Support

**Jaxaay Group**
- 📧 [jaxaay@jaxaaygroup.com](mailto:jaxaay@jaxaaygroup.com)
- 📞 [+221 78 651 15 15](tel:+221786511515)
- 🌐 [jaxaaygroup.com](https://jaxaaygroup.com)
- 📍 Parcelles Assainies U22, Villa N°529 — Dakar, Sénégal
- 🐙 [GitHub Issues](https://github.com/yessalerp/wavesn_woocommerce_pay/issues)

---

*© 2024 Jaxaay Group — Licence GPL v2 or later*
