# Οδηγός Εγκατάστασης KinderLink για Web και Mobile Android

Ο οδηγός περιγράφει τη διαδρομή από το σημερινό τοπικό περιβάλλον μέχρι:

1. ασφαλή παραγωγική εγκατάσταση του KinderLink στο web,
2. σύνδεση του mobile app με το παραγωγικό web API,
3. δημιουργία signed Android App Bundle (`.aab`),
4. δημοσίευση στο Google Play,
5. συντήρηση, backups και μελλοντικές εκδόσεις.

Ο οδηγός αφορά **νέα, ανεξάρτητη εγκατάσταση σε κενή βάση**. Δεν πρέπει να χρησιμοποιηθεί για να αντικαταστήσει υπάρχουσα σχολική εγκατάσταση χωρίς ξεχωριστό σχέδιο μεταφοράς δεδομένων και backup.

---

## 0. Τι έχουμε σήμερα και τι θα αλλάξει

### Τοπικό περιβάλλον ανάπτυξης

- Web app: Apache/Laragon, τοπικό port `8094`.
- Mobile preview: τοπικό port `5502`.
- Android app: Capacitor 7.4.2, package/application id `gr.kinderlink.mobile`.
- Android emulator: κατά την ανάπτυξη μπορεί να χρησιμοποιεί `adb reverse` και API URL `http://127.0.0.1:8094`.
- Η mobile εφαρμογή περιέχει τοπικά HTML/CSS/JS assets και επικοινωνεί με το KinderLink API με Bearer token.

### Παραγωγικό περιβάλλον

Θα χρειαστούν:

- ένα domain, π.χ. `kinderlink.example.gr`,
- hosting ή VPS με Apache 2.4, PHP 8.3+, MySQL/MariaDB και HTTPS,
- ξεχωριστή κενή production βάση,
- private environment variables,
- πραγματικός email provider με verified sender/domain,
- δημόσια σελίδα privacy policy,
- Android App Bundle υπογεγραμμένο με release key,
- λογαριασμός Google Play Console.

Στο production το mobile app πρέπει να καλεί, για παράδειγμα:

```text
https://kinderlink.example.gr
```

Δεν χρησιμοποιούμε `localhost`, `127.0.0.1`, `10.0.2.2`, port `5502` ή port `8094` μέσα στο release app.

---

# Μέρος Α: Προετοιμασία πριν από την αγορά

## 1. Αποφάσεις που πρέπει να παρθούν

Πριν αγοράσουμε υπηρεσίες, αποφασίζουμε:

- ποια θα είναι η τελική επωνυμία του σχολείου,
- ποιο domain θα χρησιμοποιηθεί,
- ποιος θα είναι ο ιδιοκτήτης του domain και του hosting,
- ποιος θα έχει πρόσβαση στο Google Play Console,
- ποιος θα διαχειρίζεται backups και λογαριασμούς,
- ποιος email provider θα στέλνει τα μηνύματα,
- σε ποια χώρα/περιοχή θα φιλοξενούνται τα δεδομένα,
- πόσο καιρό θα διατηρούνται παιδιά, φωτογραφίες και μηνύματα,
- αν η εφαρμογή θα διατίθεται δημόσια στο Google Play ή μόνο σε περιορισμένη ομάδα χρηστών.

Για σχολική εφαρμογή προτιμάται εταιρικός/οργανωτικός λογαριασμός και όχι λογαριασμός που ανήκει σε προσωπικό email εργαζομένου.

## 2. Αγορά domain

Αγοράζουμε το domain από αξιόπιστο registrar. Για ελληνικό σχολείο μπορεί να είναι `.gr` ή `.com`, ανάλογα με τη διαθεσιμότητα και τη νομική επωνυμία.

Κατά την αγορά:

1. Χρησιμοποιούμε email που ελέγχει ο οργανισμός.
2. Ενεργοποιούμε 2FA στον registrar.
3. Κρατάμε τα στοιχεία ανανέωσης και πληρωμής σε password manager.
4. Ενεργοποιούμε domain lock/transfer lock, όπου παρέχεται.
5. Ελέγχουμε τα στοιχεία κατόχου και την πολιτική απορρήτου του registrar.
6. Δεν στέλνουμε τον κωδικό του registrar σε developer, chat ή ticket.

Συνιστώμενα DNS records:

| Record | Παράδειγμα | Χρήση |
|---|---|---|
| `A` | `@ -> PUBLIC_SERVER_IP` | κύριο domain |
| `A` ή `CNAME` | `www -> kinderlink.example.gr` | προαιρετικό `www` |
| `A` ή `CNAME` | `app -> kinderlink.example.gr` | προαιρετικό subdomain |
| `MX` | του email provider | λήψη email, αν απαιτείται |
| `TXT` | SPF/DKIM/DMARC | αξιοπιστία αποστολής email |

Δεν δημοσιεύουμε database port στο Internet. Η βάση πρέπει να δέχεται συνδέσεις μόνο από τον application server ή από επιτρεπόμενη private network.

## 3. Επιλογή hosting

### Επιλογή Α: Managed PHP hosting

Κατάλληλο για απλή λειτουργία, εφόσον παρέχει:

- PHP 8.3 ή νεότερη υποστηριζόμενη έκδοση,
- `pdo_mysql`, `mbstring`, `fileinfo`, `openssl`, `curl` και κατά προτίμηση `gd`,
- Apache 2.4 με `mod_rewrite` και `.htaccess`,
- MySQL 8.0+ ή συμβατό MariaDB 10.6+,
- HTTPS/Let's Encrypt,
- cron jobs ή αξιόπιστο scheduled backup,
- αρκετό disk space για φωτογραφίες,
- database και file backups,
- έλεγχο PHP upload limits (`upload_max_filesize`, `post_max_size`),
- πρόσβαση SSH/SFTP ή ασφαλή deployment mechanism.

### Επιλογή Β: VPS

Παρέχει μεγαλύτερο έλεγχο, αλλά ο υπεύθυνος πρέπει να διαχειρίζεται:

- OS security updates,
- Apache/PHP/MySQL updates,
- firewall,
- TLS certificates,
- SSH keys και 2FA όπου υποστηρίζεται,
- backups και restore tests,
- monitoring και logs.

Για την πρώτη παραγωγική εγκατάσταση, managed hosting είναι συνήθως απλούστερο, εφόσον καλύπτει τις παραπάνω απαιτήσεις.

---

# Μέρος Β: Παραγωγικό Web App

## 4. Δημιουργία λογαριασμών υπηρεσιών

Δημιουργούμε ξεχωριστά accounts για:

- domain registrar,
- hosting/provider,
- database,
- email provider,
- Google Play Console,
- password manager ή secret manager,
- backups.

Για όλους τους λογαριασμούς:

- ενεργοποιούμε 2FA,
- χρησιμοποιούμε μοναδικούς κωδικούς,
- δεν χρησιμοποιούμε τον ίδιο κωδικό με τον πρώτο KinderLink administrator,
- κρατάμε recovery codes offline και ελεγχόμενα.

## 5. DNS και HTTPS

1. Ρυθμίζουμε το `A` record προς τη δημόσια IP του server.
2. Περιμένουμε το DNS propagation και επιβεβαιώνουμε ότι το domain δείχνει στον σωστό server.
3. Ρυθμίζουμε το Apache virtual host με document root τον φάκελο του KinderLink.
4. Ενεργοποιούμε HTTPS με Let's Encrypt ή πιστοποιητικό του hosting provider.
5. Ρυθμίζουμε redirect από HTTP σε HTTPS.
6. Επιβεβαιώνουμε ότι το πιστοποιητικό καλύπτει ακριβώς το domain που θα χρησιμοποιήσει το mobile app.
7. Ενεργοποιούμε HSTS μόνο αφού επιβεβαιωθεί ότι όλες οι απαιτούμενες διευθύνσεις λειτουργούν με HTTPS.

Production API URL:

```text
https://kinderlink.example.gr
```

Το HTTP development setting `android:usesCleartextTraffic="true"` δεν πρέπει να θεωρηθεί απαραίτητο για production. Όταν το production API λειτουργεί μόνο με HTTPS, το release build πρέπει να ελεγχθεί χωρίς cleartext traffic.

## 6. Ανέβασμα κώδικα

Κρατάμε τον κώδικα εκτός δημόσιου Git repository, εφόσον περιέχει school-specific deployment details. Χρησιμοποιούμε Git deployment, SFTP ή άλλο ελεγχόμενο μηχανισμό.

Ανεβάζουμε μόνο τα απαραίτητα production αρχεία:

- `config/`, `controllers/`, `core/`, `models/`, `views/`, `public/`, `storage/`,
- `index.php`, `.htaccess` και τα απαιτούμενα static assets.

Δεν αφήνουμε δημόσια προσβάσιμα:

- `.env.local`, `.env`, secrets ή private config,
- `database/schema.sql`, migrations και SQL dumps,
- `tools/`, test scripts και test output,
- `.git/`, logs και debug files,
- development screenshots ή sample data,
- τοπικά αρχεία `server.txt` ή προσωρινά αρχεία.

Ο φάκελος `storage/message_photos/` πρέπει να είναι writable μόνο όσο χρειάζεται από την εφαρμογή και να προστατεύεται από άμεση εκτέλεση/διάθεση αρχείων. Οι φωτογραφίες πρέπει να σερβίρονται μέσω του ελεγχόμενου endpoint της εφαρμογής, με authentication/authorization.

## 7. Δημιουργία production βάσης

1. Δημιουργούμε εντελώς νέα, κενή βάση, π.χ. `kinderlink_production`.
2. Χρησιμοποιούμε `utf8mb4` και συμβατό collation.
3. Δημιουργούμε αποκλειστικό database user.
4. Δεν χρησιμοποιούμε root από την εφαρμογή.
5. Εισάγουμε το [database/schema.sql](database/schema.sql) **μία φορά μόνο**.
6. Επιβεβαιώνουμε ότι η βάση ξεκινά χωρίς users, children, groups, photos ή school records.
7. Δεν εισάγουμε SQL dump άλλου σχολείου.
8. Μετά το schema αφαιρούμε CREATE/DROP δικαιώματα από τον runtime database user, όπου το hosting το επιτρέπει.

Το schema δεν είναι migration runner και δεν πρέπει να εκτελείται δεύτερη φορά πάνω σε μερική ή λειτουργική βάση.

## 8. Production environment variables

Δημιουργούμε το private `.env.local` στον server ή χρησιμοποιούμε secret manager. Δεν το βάζουμε στο Git και δεν το αποθηκεύουμε μέσα στο public web root χωρίς προστασία.

Ελάχιστες κατηγορίες ρυθμίσεων:

```text
APP_ENV=production
APP_URL=https://kinderlink.example.gr
APP_BASE_URL=https://kinderlink.example.gr
SESSION_NAME=<μοναδικό όνομα cookie>

DB_HOST=<private-db-host>
DB_PORT=3306
DB_NAME=kinderlink_production
DB_USER=<runtime-db-user>
DB_PASS=<ισχυρός-μοναδικός-κωδικός>
DB_CHARSET=utf8mb4

API_ALLOWED_ORIGINS=https://kinderlink.example.gr,capacitor://localhost,http://localhost
```

Οι πραγματικές μεταβλητές και τα ονόματά τους πρέπει να αντιστοιχούν στο `.env.example` και στον τρέχοντα κώδικα. Δεν αντιγράφουμε το τοπικό `.env.local` αυτούσιο στο production.

Στο `API_ALLOWED_ORIGINS` προσθέτουμε μόνο τα origins που χρειάζονται. Δεν χρησιμοποιούμε wildcard (`*`) για authenticated API.

## 9. Πρώτος administrator

Το schema πρέπει να έχει εγκατασταθεί πριν από τη δημιουργία administrator.

Εκτελούμε το `tools/create-admin.php` μόνο από ιδιωτικό CLI ή ασφαλές SSH session, με τα ειδικά `KINDERLINK_INSTALL_DB_*` environment variables που περιγράφονται στο [INSTALL.md](INSTALL.md).

Βήματα:

1. Αποκλείουμε προσωρινά πρόσβαση τρίτων στην εφαρμογή.
2. Ορίζουμε τα production DB credentials μόνο στο συγκεκριμένο CLI process.
3. Εκτελούμε το εργαλείο με το πραγματικό username/email του υπευθύνου.
4. Αποθηκεύουμε τον παραγόμενο αρχικό κωδικό μόνο σε password manager.
5. Συνδεόμαστε αμέσως.
6. Αλλάζουμε τον αρχικό κωδικό από τα Προσωπικά Στοιχεία.
7. Αφαιρούμε τα provisioning environment variables.
8. Δεν ανεβάζουμε τον κωδικό σε log, ticket, screenshot ή Git.

Δεν υπάρχει προεπιλεγμένος κοινός κωδικός και δεν πρέπει να δημιουργηθεί τέτοιος.

## 10. Ρύθμιση σχολείου και δεδομένων

Μετά το provisioning:

1. Αλλάζουμε το `school_name` στις Παραμέτρους.
2. Συμπληρώνουμε το προφίλ του administrator.
3. Δημιουργούμε μόνο τους απαραίτητους εκπαιδευτικούς και χρήστες.
4. Δημιουργούμε τμήματα και παιδιά με σωστά δικαιώματα γονικής πρόσβασης.
5. Ελέγχουμε τα `send_email1`/`send_email2` και τα email γονέων.
6. Επιβεβαιώνουμε ότι κάθε parent βλέπει μόνο τα δικά του παιδιά.
7. Δεν εισάγουμε πραγματικά δεδομένα πριν ολοκληρωθεί το access test.

## 11. Email provider και DNS email

Ξεκινάμε με `email_mode=virtual` και δοκιμαστικά δεδομένα.

Για πραγματικές αποστολές επιλέγουμε SMTP ή Brevo/άλλον εγκεκριμένο provider σύμφωνα με το υπάρχον configuration. Ρυθμίζουμε:

- verified sender email,
- verified sending domain,
- SPF,
- DKIM,
- DMARC policy,
- TLS/CA verification,
- limits και bounce handling.

Πραγματοποιούμε δοκιμή μόνο με εξουσιοδοτημένους test recipients. Ελέγχουμε χωριστά:

- ημερήσια ενημέρωση παιδιού,
- email με φωτογραφίες/ZIP link,
- free email,
- ανάκτηση username/password,
- περίπτωση absent child,
- περίπτωση αποτυχίας provider.

Το `virtual` mode δεν είναι από μόνο του εγγύηση ότι κάθε πιθανή ροή email είναι αδρανής. Ακολουθούμε την ειδική προειδοποίηση του [INSTALL.md](INSTALL.md) για τον AuthController και την ανάκτηση λογαριασμού.

## 12. Backups και επαναφορά

Ρυθμίζουμε τουλάχιστον:

- καθημερινό encrypted database backup,
- backup του `storage/message_photos/`,
- περιορισμένη πρόσβαση στα backups,
- retention policy,
- backup εκτός του ίδιου server,
- monitoring αποτυχημένων backups.

Πριν θεωρηθεί έτοιμο το production:

1. επαναφέρουμε database backup σε ξεχωριστή δοκιμαστική βάση,
2. επαναφέρουμε uploads σε ξεχωριστό φάκελο,
3. ελέγχουμε ότι η εφαρμογή ανοίγει χωρίς να εκθέτει secrets,
4. καταγράφουμε χρόνο και αποτέλεσμα restore test.

Τα backups περιέχουν προσωπικά δεδομένα και πρέπει να προστατεύονται όπως και το production.

## 13. Web acceptance test

Με HTTPS ενεργό, ελέγχουμε:

- login/logout και session expiry,
- αλλαγή κωδικού και ανάκτηση λογαριασμού,
- admin/teacher/parent access isolation,
- δημιουργία παιδιού/τμήματος/χρήστη,
- ημερήσια καταχώρηση και απουσία,
- virtual email και ελεγχόμενο real email,
- upload, προβολή και μόνιμη διαγραφή φωτογραφίας,
- ZIP link σε email,
- inbox unread counts και read state,
- responsive εμφάνιση σε κινητό και desktop,
- direct access σε `.env`, SQL, tools, storage και private files,
- HTTPS χωρίς mixed content ή certificate warnings.

Δεν συνεχίζουμε στο Play release αν αποτυγχάνει ο έλεγχος authorization ή αν το app είναι προσβάσιμο με HTTP.

---

# Μέρος Γ: Προετοιμασία Mobile Android για Production

## 14. Αλλαγή API URL

Στο mobile app το API base URL πρέπει να δείχνει στο production domain:

```text
https://kinderlink.example.gr
```

Δεν χρησιμοποιούμε το development URL:

```text
http://127.0.0.1:8094
http://10.0.2.2:8094
```

Το `adb reverse` αφορά μόνο emulator/local development και δεν υπάρχει στο production app.

Η τελική ρύθμιση πρέπει να γίνει στο mobile source πριν από το release build και να δοκιμαστεί από καθαρή εγκατάσταση. Δεν αφήνουμε τον τελικό χρήστη να χρειάζεται να γνωρίζει ή να αλλάζει το API URL.

## 15. Production Android security settings

Πριν το release:

1. Χρησιμοποιούμε μόνο HTTPS API.
2. Αφαιρούμε ή απενεργοποιούμε cleartext HTTP για το release variant, όπου είναι δυνατό.
3. Ελέγχουμε ότι δεν εμφανίζονται development endpoints, debug messages ή test credentials.
4. Ελέγχουμε ότι τα Bearer tokens αποθηκεύονται και ανακαλούνται με την προβλεπόμενη πολιτική.
5. Δεν κάνουμε log tokens, passwords, email bodies ή προσωπικά δεδομένα.
6. Ελέγχουμε logout, token expiry και login από καθαρή εγκατάσταση.
7. Επιβεβαιώνουμε ότι το app ζητά μόνο τα permissions που χρειάζεται.
8. Ελέγχουμε ότι οι φωτογραφίες επιλέγονται μέσω Android storage picker χωρίς περιττό permission για πλήρη filesystem access.

Η εφαρμογή πρέπει να συνδέεται με production API πριν δημιουργηθεί το `.aab`.

## 16. App identity και versioning

Ελέγχουμε στο `mobile-app/capacitor.config.json`:

```json
{
  "appId": "gr.kinderlink.mobile",
  "appName": "KinderLink"
}
```

Το `appId` δεν πρέπει να αλλάξει μετά την πρώτη δημοσίευση, γιατί αποτελεί τη μόνιμη ταυτότητα της εφαρμογής στο Google Play.

Για κάθε release αυξάνουμε το Android `versionCode` και ενημερώνουμε το `versionName`, για παράδειγμα:

```text
versionName: 1.0.0
versionCode: 1
```

Το επόμενο upload πρέπει να έχει μεγαλύτερο `versionCode`, π.χ. `2`.

Το τελικό όνομα, icon, splash screen, screenshots και περιγραφή πρέπει να συμφωνούν με το πραγματικό KinderLink και τη νομική ταυτότητα του publisher.

## 17. Android signing key

Για το Google Play χρειάζεται release signing key/keystore.

1. Δημιουργούμε το keystore σε ασφαλή υπολογιστή ή secret manager.
2. Χρησιμοποιούμε ισχυρό password.
3. Κρατάμε ασφαλές αντίγραφο του keystore και των passwords.
4. Δεν το βάζουμε στο Git, στο public hosting ή σε chat.
5. Δεν το διαγράφουμε μετά το πρώτο release.
6. Ρυθμίζουμε Play App Signing σύμφωνα με την τρέχουσα διαδικασία του Google Play Console.

Η απώλεια του signing material ή των recovery διαδικασιών μπορεί να επηρεάσει σοβαρά μελλοντικές ενημερώσεις. Καταγράφουμε ποιος οργανισμός κατέχει το account και τα keys.

## 18. Δημιουργία Android App Bundle

Από το `mobile-app`:

```powershell
npm install
npx cap sync android
npx cap open android
```

Στο Android Studio:

1. Ελέγχουμε το production API URL.
2. Ελέγχουμε package id και version code.
3. Ελέγχουμε ότι το app φορτώνει χωρίς local server.
4. Επιλέγουμε `Build` -> `Generate Signed Bundle / APK`.
5. Επιλέγουμε `Android App Bundle`.
6. Επιλέγουμε το release keystore.
7. Δημιουργούμε το signed `.aab`.
8. Κρατάμε το αρχείο σε ιδιωτικό release folder και όχι μέσα στο Git.

Το `npx cap sync android` πρέπει να εκτελείται κάθε φορά που αλλάζουν τα αρχεία `mobile-app/www/*`. Διαφορετικά το Android build μπορεί να περιέχει παλιά assets.

Πριν το upload ελέγχουμε το `.aab` με καθαρή συσκευή ή emulator:

- εγκατάσταση χωρίς dev server,
- login με test account,
- dashboard και parent/teacher/admin ροές,
- inbox και unread badge,
- create message, attendance και email test,
- photo picker/upload/delete,
- logout/login,
- offline/error state όταν το API δεν είναι διαθέσιμο.

## 19. Google Play Console account

1. Δημιουργούμε ή χρησιμοποιούμε οργανωτικό Google account.
2. Εγγραφόμαστε στο Google Play Console και ολοκληρώνουμε τα στοιχεία publisher.
3. Πληρώνουμε το απαιτούμενο registration fee, αν ζητηθεί.
4. Ολοκληρώνουμε identity/organization verification.
5. Ενεργοποιούμε 2FA και περιορίζουμε τα μέλη της ομάδας με role-based access.
6. Ελέγχουμε τις τρέχουσες απαιτήσεις του Play Console για νέο personal ή organization account, testing και production access.

Οι ακριβείς απαιτήσεις της Google αλλάζουν. Ακολουθούμε τα warnings και τα deadlines που εμφανίζονται στο ίδιο το Play Console.

## 20. Δημιουργία εφαρμογής στο Play Console

Στο Play Console:

1. Επιλέγουμε `Create app`.
2. Όνομα εφαρμογής: `KinderLink` ή η τελική εγκεκριμένη ονομασία.
3. Default language: η γλώσσα που θα χρησιμοποιηθεί για την κύρια καταχώρηση.
4. Επιλέγουμε ότι είναι app και όχι game.
5. Επιλέγουμε δωρεάν ή paid σύμφωνα με το business model.
6. Δηλώνουμε ότι η εφαρμογή περιέχει/χειρίζεται user data σύμφωνα με τις πραγματικές λειτουργίες.
7. Δεν δημιουργούμε ψευδή claims για educational certification, encryption ή data deletion.

Δεν χρησιμοποιούμε δοκιμαστικούς λογαριασμούς ή πραγματικά στοιχεία παιδιών στα screenshots, store listing ή review notes.

## 21. Υποχρεωτικές/απαραίτητες καταχωρήσεις Store

Προετοιμάζουμε:

- app icon σύμφωνα με τις τρέχουσες προδιαγραφές Play,
- short description,
- full description,
- screenshots από πραγματική production-like έκδοση,
- feature graphic, αν ζητηθεί από το Console,
- support email,
- δημόσιο privacy policy URL,
- website URL, αν υπάρχει,
- app access instructions για reviewers.

Η privacy policy πρέπει να είναι δημόσια προσβάσιμη χωρίς login και να εξηγεί τουλάχιστον:

- ποια δεδομένα συλλέγονται,
- δεδομένα παιδιών/γονέων/εκπαιδευτικών και φωτογραφίες,
- γιατί χρησιμοποιούνται,
- ποιος είναι ο υπεύθυνος επεξεργασίας,
- retention/deletion policy,
- τρίτους email/hosting providers,
- δικαιώματα χρηστών και στοιχεία επικοινωνίας,
- διαδικασία αιτήματος διαγραφής, όπου απαιτείται.

Η privacy policy και τα Data Safety answers πρέπει να ταιριάζουν με την πραγματική συμπεριφορά του web και mobile app. Δεν αντιγράφουμε generic policy χωρίς νομικό έλεγχο.

## 22. Data Safety, content rating και target audience

Συμπληρώνουμε με ακρίβεια:

- Data Safety form,
- app access/credentials για reviewers,
- content rating questionnaire,
- target audience/age declarations,
- advertising declaration,
- permissions declaration, αν ζητηθεί.

Επειδή το KinderLink χειρίζεται δεδομένα παιδιών και φωτογραφίες, ο υπεύθυνος λειτουργίας πρέπει να πάρει νομική/προστασίας δεδομένων συμβουλή πριν δηλώσει την εφαρμογή ως production-ready. Η τεχνική λειτουργία δεν αποτελεί από μόνη της συμμόρφωση με GDPR ή άλλον κανονισμό.

## 23. Testing tracks

Δεν ξεκινάμε αμέσως με production rollout.

Προτεινόμενη σειρά:

1. `Internal testing`: μικρή ομάδα του οργανισμού.
2. `Closed testing`: επιλεγμένοι εκπαιδευτικοί/γονείς με συνθετικό ή εξουσιοδοτημένο test data.
3. `Open testing`, μόνο αν χρειάζεται και αν η πολιτική του οργανισμού το επιτρέπει.
4. `Production`: σταδιακή κυκλοφορία.

Σε κάθε track ελέγχουμε:

- εγκατάσταση και update,
- login από καθαρή συσκευή,
- token expiry/logout,
- Android versions και διαφορετικά screen sizes,
- upload φωτογραφίας,
- email test,
- permissions και privacy wording,
- crash/error reports.

Τα review credentials πρέπει να είναι test accounts με ελάχιστα δικαιώματα και χωρίς πραγματικά προσωπικά δεδομένα.

## 24. Production rollout

Πριν το production rollout:

- επιβεβαιώνουμε ότι το web domain και API είναι σταθερά,
- επιβεβαιώνουμε ότι η release έκδοση δεν δείχνει localhost,
- επιβεβαιώνουμε ότι το privacy URL ανοίγει,
- επιβεβαιώνουμε ότι το Play listing είναι ακριβές,
- έχουμε rollback plan για το web API,
- έχουμε διαθέσιμο backup και restore test,
- ενημερώνουμε τους χρήστες για το πώς θα λάβουν την εφαρμογή.

Χρησιμοποιούμε staged rollout, όπου προσφέρεται, και παρακολουθούμε crashes, failed login, API errors και email/photo failures πριν αυξήσουμε το ποσοστό χρηστών.

---

# Μέρος Δ: Διαδικασία κάθε επόμενης έκδοσης

## 25. Web release

Για κάθε web αλλαγή:

1. Κάνουμε αλλαγή σε development.
2. Τρέχουμε PHP lint και τα σχετικά tests.
3. Ελέγχουμε migrations σε αντίγραφο βάσης, ποτέ απευθείας πρώτη φορά σε production.
4. Παίρνουμε database και uploads backup.
5. Κρατάμε release tag/commit.
6. Ανεβάζουμε κώδικα με ελεγχόμενο deployment.
7. Εκτελούμε smoke test login, dashboard, inbox, email και photo flow.
8. Παρακολουθούμε logs και errors.

## 26. Mobile release

Για κάθε mobile αλλαγή:

1. Ενημερώνουμε το `mobile-app/www`.
2. Ελέγχουμε ότι το API contract παραμένει backward-compatible.
3. Εκτελούμε `npx cap sync android`.
4. Αυξάνουμε `versionCode` και, όταν χρειάζεται, `versionName`.
5. Δημιουργούμε νέο signed `.aab`.
6. Δοκιμάζουμε internal/closed track.
7. Ελέγχουμε Android release χωρίς local server.
8. Ανεβάζουμε στο Play Console ως νέα release.
9. Χρησιμοποιούμε staged rollout.
10. Δεν διαγράφουμε το παλιό signing material.

Το web API πρέπει να υποστηρίζει προσωρινά και την προηγούμενη έκδοση mobile, όταν είναι πρακτικά δυνατό, ώστε οι χρήστες να μπορούν να ενημερώσουν την εφαρμογή χωρίς να μείνουν αποκλεισμένοι.

---

# Μέρος Ε: Τελικό checklist πριν δοθεί σε σχολείο

## Web

- [ ] Domain ανήκει στον οργανισμό.
- [ ] HTTPS λειτουργεί χωρίς warnings.
- [ ] Production βάση είναι νέα και κενή πριν το provisioning.
- [ ] Δεν υπάρχει root DB user στην εφαρμογή.
- [ ] `.env`, SQL, tools, logs και Git δεν είναι public.
- [ ] Backups βάσης και uploads είναι ενεργά.
- [ ] Έγινε restore test.
- [ ] `school_name` και administrator profile είναι σωστά.
- [ ] SMTP/Brevo sender/domain είναι verified.
- [ ] SPF/DKIM/DMARC έχουν ελεγχθεί.
- [ ] Parent authorization test πέρασε.
- [ ] Photo access/delete test πέρασε.
- [ ] Privacy policy και retention policy έχουν εγκριθεί.

## Android

- [ ] Production API URL είναι HTTPS domain.
- [ ] Δεν υπάρχει `localhost`, `127.0.0.1`, `10.0.2.2` ή local port στο release.
- [ ] Release build δεν βασίζεται σε dev server.
- [ ] Package id `gr.kinderlink.mobile` έχει ελεγχθεί.
- [ ] `versionCode` είναι μοναδικό και αυξημένο.
- [ ] Release keystore έχει ασφαλές backup.
- [ ] Signed `.aab` εγκαταστάθηκε σε καθαρή συσκευή.
- [ ] Login, inbox, email και photo upload δοκιμάστηκαν.
- [ ] Privacy policy URL είναι δημόσιο.
- [ ] Play Data Safety και declarations είναι ακριβή.
- [ ] Internal/closed testing πέρασε.
- [ ] Έχει οριστεί staged rollout και rollback plan.

## Λειτουργία μετά το launch

- [ ] Υπάρχει ένας υπεύθυνος για τεχνικά incidents.
- [ ] Υπάρχει ένας υπεύθυνος για λογαριασμούς/δικαιώματα.
- [ ] Ελέγχονται backups καθημερινά.
- [ ] Παρακολουθούνται disk space, φωτογραφίες, email bounces και errors.
- [ ] Αλλάζονται/ανακαλούνται credentials όταν αποχωρεί προσωπικό.
- [ ] Γίνεται περιοδικός έλεγχος χρηστών και πρόσβασης γονέων.
- [ ] Καταγράφονται αλλαγές schema και release versions.

---

## Σημαντική σημείωση

Η δημοσίευση στο Google Play και η λειτουργία μιας σχολικής εφαρμογής με δεδομένα παιδιών απαιτούν, εκτός από τεχνική εγκατάσταση, έλεγχο νομικής συμμόρφωσης, προστασίας προσωπικών δεδομένων και συμβάσεων με hosting/email providers. Ο παρών οδηγός είναι τεχνικό runbook και δεν αντικαθιστά νομική συμβουλή, security review ή τις τρέχουσες οδηγίες του Google Play Console.
