<p align="center">
  <img src="favicons/512.png" width="150" alt="Logo Yalper">
</p>

<h1 align="center">Yalper LiveReplay</h1>

<p align="center">
  <strong>L'azione succede una volta. Con Yalper puoi rivederla, condividerla e farla vivere ancora.</strong>
</p>

<p align="center">
  <img alt="PHP 8.3" src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white">
  <img alt="Ubuntu 24.04" src="https://img.shields.io/badge/Ubuntu-24.04-E95420?logo=ubuntu&logoColor=white">
  <img alt="MariaDB" src="https://img.shields.io/badge/MariaDB-ready-003545?logo=mariadb&logoColor=white">
  <img alt="FFmpeg" src="https://img.shields.io/badge/FFmpeg-powered-007808?logo=ffmpeg&logoColor=white">
  <img alt="PWA" src="https://img.shields.io/badge/PWA-installable-5A0FC8?logo=pwa&logoColor=white">
</p>

---

## Il replay, finalmente nelle tue mani

Yalper è una piattaforma **LiveReplay web** pensata per catturare i momenti che meritano una seconda visione: un gol, una giocata, un arrivo, un'esibizione o qualunque istante che non può aspettare il montaggio del giorno dopo.

Funziona direttamente dal tuo smartphone, trasforma le registrazioni in contenuti riproducibili e condivisibili e accompagna tutto il flusso, dalla cattura fino allo streaming HLS. Niente applicazioni mastodontiche e niente pipeline misteriose: Yalper è un sistema concreto, costruito sul campo e affinato intorno a un'esigenza reale.

> **Registra adesso. Elabora automaticamente. Rivivi subito.**

## Perché Yalper è speciale

- **Replay immediati dal browser** — cattura gli ultimi secondi dell'azione senza interrompere ciò che stai riprendendo.
- **Modalità live dedicata** — una pipeline separata elabora le sessioni live e genera playlist pronte per la riproduzione.
- **Coda upload resistente** — i contenuti in attesa restano in IndexedDB e possono ripartire quando la connessione torna disponibile.
- **Sessioni OTT multiple** — un nuovo login non annulla più le sessioni precedenti: anche una vecchia coda può completare correttamente i suoi upload.
- **Elaborazione automatica con FFmpeg** — i demoni prendono in carico i job, producono segmenti, playlist e anteprime senza lavoro manuale.
- **Streaming HLS e condivisione** — replay e dirette diventano contenuti facili da guardare e distribuire.
- **PWA installabile** — esperienza a schermo intero su dispositivi compatibili, senza passare da uno store.
- **Telecomando e modalità gaming** — Pusher abilita comandi ed eventi in tempo reale per flussi più dinamici.

## Nato artigianale. Diventato tenace.

Yalper non nasce da un generatore di boilerplate: è software fatto a mano, cresciuto iterazione dopo iterazione intorno all'utilizzo reale. Dietro l'interfaccia essenziale c'è una catena completa:

```text
Browser → coda locale → upload a chunk → job MariaDB
        → demone FFmpeg → HLS e thumbnail → replay condivisibile
```

Questa architettura permette di separare la cattura dall'elaborazione pesante. Il browser rimane concentrato sulla registrazione, mentre il server lavora in background e prepara il risultato finale.

## Perfetto quando il momento conta

Yalper dà il meglio in contesti come:

- sport amatoriale e allenamenti;
- tornei, eventi e competizioni locali;
- esibizioni, spettacoli e performance;
- postazioni di regia leggere;
- installazioni interattive e gaming;
- sperimentazione video dal vivo.

## Due motori, una sola esperienza

| Componente | Ruolo |
|---|---|
| `capture.html` | Cattura e gestione dei replay |
| `capture_live.html` | Acquisizione delle sessioni live |
| `upload/save.php` | Ricezione sicura dei chunk |
| `jobs.php` | Creazione e gestione dei lavori |
| `demondb.php` | Elaborazione automatica dei replay |
| `demonlive.php` | Elaborazione dedicata alle dirette |
| `sharing.php` | Riproduzione e condivisione replay |
| `sharing_live.php` | Riproduzione e condivisione live |

## Installazione completa su Ubuntu 24.04

Yalper include un installer che prepara Apache, PHP 8.3, MariaDB, FFmpeg, Composer, cron e, se richiesto, TLS con Certbot.

```bash
git clone git@github.com:yellowelise/yalper_it.git
cd yalper_it
cp install/install.conf.example install/install.conf
chmod 600 install/install.conf
nano install/install.conf
sudo bash install/install.sh
```

Lo script crea database e utente, importa lo **schema privo di dati**, applica la migrazione delle sessioni OTT, configura il virtual host e prepara i demoni cron con lock anti-sovrapposizione.

Per ogni dettaglio operativo, backup, collaudo e rollback consulta [ISTRUZIONI_DEPLOY.md](ISTRUZIONI_DEPLOY.md).

## Sicurezza senza segreti nel codice

La configurazione sensibile vive in file esterni al webroot. Il repository contiene soltanto modelli senza credenziali e uno schema SQL vuoto: nessun account, token, commento o job applicativo viene distribuito con il sorgente.

Tra le protezioni già previste:

- password applicative con hash moderno;
- token di sessione memorizzati come hash;
- sessioni revocabili e con scadenza;
- query preparate nei flussi aggiornati;
- demoni non richiamabili via HTTP;
- cron protetti da `flock`;
- segreti e dump dati esclusi da Git.

## La filosofia Yalper

La tecnologia migliore è quella che sparisce mentre stai facendo qualcosa di importante. Yalper vuole essere esattamente questo: **una memoria video sempre pronta**, abbastanza leggera da stare in un browser e abbastanza completa da portare un momento dalla videocamera allo streaming.

Non cerca di essere l'ennesima piattaforma video generalista. Fa una cosa precisa e la fa con carattere: **prendere l'azione appena successa e restituirtela come replay**.

---

<p align="center">
  <strong>Yalper LiveReplay</strong><br>
  Il momento passa. Il replay resta.
</p>
