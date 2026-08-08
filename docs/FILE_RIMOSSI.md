# Inventario della pulizia

La pulizia ha rimosso 93 file senza eliminare gli upload, i media musicali usati dai demoni o le funzioni feed/gaming/remoter. Il dump DB con dati e stato sostituito da `database/schema.sql`, che contiene soltanto la struttura.

## Rimossi

- Endpoint diagnostici o gia disattivati: `check.php`, `info.php`, `send.php`, `API/logmein.php`, `API/propagaSalva.php`.
- Ricarica PayPal disattivata: `ricarica.php` e directory `ricarica/`.
- Demoni sostituiti dai due dichiarati attivi: tutte le varianti `old`, `ok`, `pregpt`, `premod`, `verbose`, `valid`, `no_audio`, oltre a `demon.php` e `demonpip.php`.
- Pagine indice chiaramente storiche/sperimentali: varianti `old`, `LAST`, `ok`, `ia2`, `canva`, `stravolto`, `ooindex` e `index2`.
- Player/condivisioni storici non referenziati: varianti `old`, `default`, `plyr`, `wall`, `sharing1`, `sharing3`, `ds_sharing`, `good_sharing`.
- Copie JavaScript di sviluppo non referenziate: varianti `AI`, `GEMINI`, `SUPER`, `pre*`, `old`, `ok*`, `cursor`, `stravolto`, `canva`, `yalper2` e simili.
- Utility/test non referenziati: pagine camera/upload/sessione, demo `sun`, `molecole`, `sr`, conversioni manuali e vecchi helper RecordRTC.
- Wrapper DB/config root non referenziati: `db.php` e `config.php`; il solo ingresso DB rimane `config/db.php`.
- Dump `yalperit_db.mysql.sql` con dati reali, sostituito dallo schema privo di righe `database/schema.sql`.

## Mantenuti per prudenza

- `gallery.php`, `settings.php` e `youtube_sharing.php`, perche rappresentano funzioni complete anche se non collegate dal flusso principale.
- `feed/`, `gaming/`, `remoter/` e `dashboard/`.
- `sharing_live.php`, richiamato dal flusso live.
- `upload/index.php`, che impedisce l'indicizzazione diretta delle directory create dai demoni.
- `database/schema.sql`, riferimento strutturale senza dati.
- `upload/base0.mp3` fino a `upload/base12.mp3`, usati casualmente dai due demoni.
