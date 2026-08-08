# Yalper: installazione e deploy

Queste istruzioni descrivono il nucleo attivo dell'applicazione dopo la pulizia del codice legacy.

## Installazione automatica su Ubuntu 24.04

Il metodo consigliato e l'installer incluso. Eseguirlo da una copia del progetto, preferibilmente fuori dal document root:

```bash
cp install/install.conf.example install/install.conf
chmod 600 install/install.conf
nano install/install.conf
sudo bash install/install.sh
```

In alternativa, al primo avvio lo script crea automaticamente `install/install.conf` dal modello e si ferma per consentirne la compilazione.

L'installer:

- installa Apache, PHP 8.3, MariaDB, FFmpeg, Composer, cron e Certbot;
- crea utente, database e file dei segreti;
- importa `database/schema.sql`, privo di dati, soltanto su un database vuoto;
- crea il primo account attivo indicato da `ADMIN_EMAIL` soltanto se `users` e vuota;
- applica la migrazione OTT;
- non pubblica dump con dati nel webroot e protegge gli upload esistenti;
- configura Apache, PHP, cron con `flock`, logrotate e permessi; i cron partono solo con `ENABLE_CRON='yes'`;
- crea un backup prima di aggiornare un'installazione esistente;
- puo richiedere il certificato TLS quando `ENABLE_TLS='yes'` e il DNS e gia corretto.

Dopo la prima esecuzione conserva la configurazione privata in `/home/yalperit/.config/yalper/install.conf`, che viene preferita automaticamente alle esecuzioni successive. Le sezioni seguenti documentano gli stessi passaggi per controllo o installazione manuale.

Lo script non modifica il firewall per non rischiare di interrompere SSH. Se UFW e attivo, verificare prima l'accesso SSH e poi aprire il profilo web:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Apache Full'
sudo ufw status
```

## Componenti attivi

- `index.html`: ingresso della PWA e controllo della sessione.
- `login.html`: login e creazione della sessione OTT.
- `capture.html`: registrazione replay; carica `js/yalper_nocompr.js`.
- `capture_live.html`: registrazione live; carica `js/live_stream.js`.
- `upload/save.php` e `jobs.php`: ricezione dei chunk e creazione del job.
- `demondb.php`: elaborazione dei replay.
- `demonlive.php`: elaborazione live. Nel repository il nome non contiene underscore.
- `sharing.php` e `sharing_live.php`: riproduzione/condivisione.
- `feed/`, `gaming/` e `remoter/`: funzioni accessorie mantenute.

## Requisiti

- PHP 7.4 o successivo con `mysqli`, `json`, `mbstring`, `fileinfo` e `curl`.
- MySQL/MariaDB.
- `ffmpeg` e `ffprobe` disponibili nel `PATH` dell'utente cron.
- Composer.
- Apache 2.4 con `.htaccess` abilitato. Con Nginx le regole di blocco vanno replicate nella configurazione del virtual host.

Installare le dipendenze PHP dalla root del progetto:

```bash
composer install --no-dev --optimize-autoloader
```

## 1. File dei segreti

I segreti non devono essere presenti nel webroot o nel repository.

Creare la directory esterna e copiare il modello:

```bash
mkdir -p /home/yalperit/.config/yalper
cp config/secrets.example.php /home/yalperit/.config/yalper/secrets.php
chmod 700 /home/yalperit/.config/yalper
chmod 600 /home/yalperit/.config/yalper/secrets.php
```

Modificare `/home/yalperit/.config/yalper/secrets.php` inserendo:

- host, nome, utente e password del database;
- key, secret, app ID e cluster Pusher.

Il file deve continuare a restituire un array PHP come `config/secrets.example.php`. Non usare le vecchie credenziali presenti nelle copie storiche del progetto: DB, Pusher e PayPal vanno ruotati.

Per sviluppo locale si puo creare `config/secrets.local.php`; il file e ignorato da `.gitignore`.

## 2. Backup e migrazione OTT

Prima della migrazione creare un backup del database:

```bash
mysqldump --single-transaction --routines --triggers NOME_DB > /percorso/esterno/yalper-prima-sessioni.sql
```

Eseguire poi:

```bash
mysql NOME_DB < database/migrations/001_create_user_sessions.sql
```

La migrazione crea `user_sessions` e importa gli OTT correnti. Va eseguita prima di fare un nuovo login con il codice aggiornato: in questo modo una coda IndexedDB ancora associata al vecchio OTT continua a essere autorizzata. Ogni nuovo login crea una sessione aggiuntiva con validita di 30 giorni invece di sovrascrivere le precedenti.

Verifica minima:

```sql
SHOW TABLES LIKE 'user_sessions';
SELECT COUNT(*) FROM user_sessions;
```

## 3. Percorsi e permessi

I due demoni ricavano i percorsi da `__DIR__`, quindi seguono automaticamente `APP_ROOT` configurato nell'installer.

In installazione manuale, creare la directory degli upload e renderla scrivibile dall'utente PHP e dall'utente cron:

```bash
mkdir -p /home/yalperit/web/yalper.it/public_html/upload/uploads
```

Non impostare permessi `777`. Usare utente/gruppo del server web e permessi `750`/`770` secondo la configurazione dell'hosting.

`database/schema.sql` contiene esclusivamente la struttura delle tabelle: nessun utente, token, commento, job o altro dato applicativo. I backup creati con `mysqldump` devono restare fuori dal webroot.

## 4. Cron

Usare `flock` per evitare due elaborazioni contemporanee dello stesso demone. Creare prima una directory log fuori dal webroot:

```bash
mkdir -p /home/yalperit/logs
```

Esempio crontab, adattando il percorso di PHP se necessario:

```cron
* * * * * flock -n /home/yalperit/.cache/yalper/demondb.lock /usr/bin/php /home/yalperit/web/yalper.it/public_html/demondb.php >> /home/yalperit/logs/demondb.log 2>&1
* * * * * flock -n /home/yalperit/.cache/yalper/demonlive.lock /usr/bin/php /home/yalperit/web/yalper.it/public_html/demonlive.php >> /home/yalperit/logs/demonlive.log 2>&1
```

La regola `.htaccess` impedisce di avviare i demoni via HTTP, ma non ne ostacola l'esecuzione CLI.

## 5. Ordine di pubblicazione

1. Eseguire il backup DB.
2. Creare e verificare il file esterno dei segreti.
3. Eseguire la migrazione `001_create_user_sessions.sql`.
4. Pubblicare codice, `.htaccess` e dipendenze Composer.
5. Verificare i permessi di `upload/uploads`.
6. Installare o aggiornare i due cron con `flock`.
7. Effettuare un login di prova senza cancellare localStorage/IndexedDB.

## 6. Collaudo rapido

Controllare nell'ordine:

1. login replay e apertura di `capture.html`;
2. caricamento di un replay corto;
3. creazione della riga in `jobs`;
4. elaborazione da parte di `demondb.php`;
5. presenza di playlist `.m3u8`, segmenti `.ts` e thumbnail;
6. riproduzione e download;
7. secondo login dallo stesso dispositivo e completamento di un upload rimasto in coda;
8. modalità live e `demonlive.php`;
9. risposta `403` o `404` tentando via HTTP un file `*demon*.php` o `yalperit_db.mysql.sql`.

## 7. Ricarica e integrazioni opzionali

La ricarica PayPal e stata rimossa: non esiste piu un endpoint capace di accreditare crediti.

Pusher resta necessario per gaming/remoter. Gli endpoint validano evento e tag, ma il telecomando resta intenzionalmente utilizzabile senza login. Se deve essere esposto pubblicamente, il passo successivo consigliato e aggiungere un PIN o una sessione dedicata.

L'integrazione YouTube e stata mantenuta ma richiede, se utilizzata:

```text
/home/yalperit/.config/yalper/google_client_secret.json
/home/yalperit/.config/yalper/youtube_token.json
```

## 8. Rollback

- Non cancellare `user_sessions`: la tabella e aggiuntiva e puo rimanere anche durante un rollback del codice.
- Ripristinare prima i file applicativi, poi verificare login e upload.
- Un rollback al vecchio login torna a sovrascrivere `users.OTT` e puo quindi perdere nuovamente l'autorizzazione delle code precedenti.
- Conservare backup DB, segreti e log fuori dal webroot.
