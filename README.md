# DiscStats

DiscStats ir disku golfa tīmekļa lietotne spēlētājiem un sacensību organizatoriem:

- **Treniņu raundi** — spēlētājs izvēlas trasi kartē (OpenStreetMap), uzaicina draugus un vairāki telefoni vienlaikus ved rezultātu metienu pa metienam vai ar vienu skaitli bedrītei.
- **Sacensības** — organizators izveido turnīru ar divīzijām un dalībnieku limitu, spēlētāji piesakās, 30 minūtes pirms starta sistēma izlozē grupas (kartītes) un shotgun starta bedrītes, raunda laikā katrs grupas loceklis ved rezultātus un tiek parādīts live rezultātu saraksts.
- **Reitingi** — pēc turnīra tiek aprēķināts katra raunda reitings, trases (layout) reitings un spēlētāja reitings (PDGA / Disc Golf Metrix stilā, sk. `app/Support/RatingEngine.php`).
- **Trašu karte** — disku golfa trases no OpenStreetMap, nosaukumi papildināti no Disc Golf Metrix un apkārtnes, rokām pārbaudīti layouti Latvijas trasēm (`app/Support/CuratedCourses.php`).
- **Vingrinājumu bibliotēka** — publiski un privāti treniņu vingrinājumi ar komentāriem un saglabāšanu.

## Prasības

| Rīks | Versija |
| --- | --- |
| PHP | 8.2+ ar paplašinājumiem `pdo_mysql`, `mbstring`, `fileinfo`, `openssl` (testiem arī `pdo_sqlite`) |
| Composer | 2.x |
| Node.js | 20.19+ vai 22.12+ (Vite 7) |
| Datubāze | MySQL 8 vai MariaDB 10.6+ (Laragon noklusējuma MySQL der) |

Mērķa datubāze ir **MySQL**. Automātiskie testi izmanto SQLite atmiņā (`phpunit.xml`), tāpēc migrācijas ir rakstītas tā, lai darbotos abās.

## Instalācija

1. Izveido tukšu datubāzi, piemēram `discstats`.
2. Klonē repozitoriju un palaid:

   ```bash
   composer setup
   ```

   Tas izdara visu pēc kārtas: `composer install`, nokopē `.env.example` uz `.env` (ja `.env` vēl nav), ģenerē `APP_KEY`, palaiž migrācijas, palaiž seederus (`db:seed`), izveido `public/storage` saiti avatariem (`storage:link`), `npm install` un `npm run build`.

   Pirms tam `.env` failā jāieraksta datubāzes piekļuve (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). Ja `.env` vēl nav, vari vispirms palaist `copy .env.example .env` (Windows) vai `cp .env.example .env`.

Ja gribi soļus darīt pa vienam:

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan storage:link
npm install
npm run build
```

### Ko izveido seederi

`php artisan db:seed` drīkst palaist atkārtoti — jau esoši dati netiek dublēti vai pārrakstīti.

| Seederis | Ko izveido |
| --- | --- |
| `AdminUserSeeder` | Pirmo administratoru no `ADMIN_NAME` / `ADMIN_EMAIL` / `ADMIN_PASSWORD` (`.env`). Ja `ADMIN_PASSWORD` ir tukšs, tiek ģenerēta nejauša parole un **vienreiz** izdrukāta konsolē. Ja administrators jau eksistē, nekas netiek mainīts. |
| `GuestUserSeeder` | Koplietoto tikai lasāmo kontu pogai "Continue as guest". |
| `CategorySeeder`, `ExerciseSeeder` | Vingrinājumu kategorijas un sākuma vingrinājumu bibliotēku. |
| `DemoUserSeeder` | **Tikai ne-production vidē:** testa kontus `verified@discstats.com` / `verified123` un `player@discstats.com` / `player123`. Production vidē (`APP_ENV=production`) tie netiek veidoti. |

Pēc pirmās ielogošanās administratoram ieteicams nomainīt ģenerēto paroli.

## Palaišana

Izstrādes režīmā viss vajadzīgais startē ar vienu komandu:

```bash
composer dev
```

Tā palaiž `php artisan serve`, queue worker, **scheduler** (`schedule:work`), logu skatītāju un Vite.

### Scheduler ir obligāts

Sacensību dzīves cikls un trašu kartes atjaunošana notiek fonā:

| Komanda | Kad | Ko dara |
| --- | --- | --- |
| `competitions:sync` | katru minūti | 30 min pirms starta izlozē grupas un izveido bedrītes, starta brīdī atzīmē sacensības kā "live" |
| `courses:warm` | katru dienu 04:30 | iepriekš ielādē trašu karti no OpenStreetMap un atrod nosaukumus nenosauktajām trasēm |

Production serverī schedulerim vajag vienu cron ierakstu:

```
* * * * * cd /ceļš/uz/discstats && php artisan schedule:run >> /dev/null 2>&1
```

Windows serverī to pašu dara Task Scheduler uzdevums, kas katru minūti izpilda `php artisan schedule:run`.

### Noderīgas komandas

```bash
php artisan competitions:sync        # sacensību dzīves cikls uzreiz (to pašu dara scheduler)
php artisan ratings:recalculate      # pārrēķina visus reitingus no pabeigtajiem turnīriem
php artisan courses:warm LV,EE       # ielādē trašu karti norādītajām valstīm
php artisan courses:name LV          # atrod nosaukumus nenosauktajām trasēm
```

## Lomas

| Loma | Ko drīkst |
| --- | --- |
| `guest` | Viens koplietots konts pogai "Continue as guest": var tikai skatīties, neko nevar saglabāt. |
| `user` | Jauns reģistrēts spēlētājs: treniņi, pieteikšanās sacensībām, privāti vingrinājumi. |
| `verified` | Spēlētājs ar pietiekamu sacensību pieredzi: var arī veidot sacensības (tās apstiprina admins) un publicēt vingrinājumus. |
| `admin` | Visu pārvalda: apstiprina sacensības, labo rezultātus, pārvalda lietotājus un saturu. |

**Svarīgi:** loma `verified` DiscStats nozīmē *spēlētāja sacensību pieredzi*, **nevis** apstiprinātu e-pastu. Laravel kolonna `users.email_verified_at` ar šo lomu nav saistīta, un e-pasta verifikācija lietotnē netiek izmantota.

Pirmās sacensības svaigā sistēmā izveido administrators (vai demo konts `verified@discstats.com` lokālajā vidē), jo parasts lietotājs par `verified` kļūst tikai pēc sacensībām.

## Drošība un piekļuve

- **Guest konts** (`guest@discstats.local`) tiek izveidots instalācijas laikā. Domēns `@discstats.local` ir rezervēts: to nevar izmantot ne reģistrācijā, ne admin panelī, guest kontā nevar ielogoties ar paroli, un "Continue as guest" nekad neielogo kontā, kuram nav `guest` lomas.
- **Pieteikšanās ierobežojumi:** pēc 5 nepareizām parolēm vienam e-pastam pieteikšanās tiek bloķēta uz minūti; login, reģistrācijai, guest pieejai, paroles atjaunošanai, vingrinājumu veidošanai un komentāriem ir pieprasījumu limiti (`app/Providers/AppServiceProvider.php`).
- **Paroles atjaunošana:** login lapā "Forgot password?" nosūta saiti uz e-pastu (derīga 60 minūtes). Ar `MAIL_MAILER=log` vēstule netiek sūtīta, bet ierakstīta `storage/logs/laravel.log`, no kurienes saiti var nokopēt; īstai sūtīšanai `.env` jānorāda SMTP iestatījumi.
- **Privāti vingrinājumi** ir redzami tikai autoram un administratoriem. Noteikums ir vienuviet (`app/Policies/ExercisePolicy.php`) un attiecas uz skatīšanu, saglabāšanu un komentēšanu, arī tad, ja kāds atver vingrinājumu pēc ID.

## Testi

Testi izmanto SQLite datubāzi atmiņā, tāpēc PHP vajag `pdo_sqlite` paplašinājumu. Laragon to ieslēdz `php.ini` failā, noņemot semikolu rindas sākumā:

```ini
extension=pdo_sqlite
```

Tad:

```bash
php artisan test
```

Ja paplašinājumu nevar ieslēgt pastāvīgi, testus var palaist arī tā:

```bash
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit
```

## Ārējie servisi

Trašu kartei tiek izmantoti publiski, bezmaksas servisi. Atbildes tiek kešotas, lai tos nenoslogotu:

- **OpenStreetMap Overpass API** — trašu atrašanās vietas un bedrīšu dati;
- **Disc Golf Metrix** (`api.php?content=courses_list`) — trašu nosaukumi nenosauktajām OSM trasēm;
- **Nominatim** — tuvākās apdzīvotās vietas nosaukums, ja citur nosaukumu atrast neizdodas.

Ja šie servisi nav pieejami, karte rāda pēdējos kešotos datus vai rokām pārbaudītās trases.

## Projekta struktūra

| Vieta | Saturs |
| --- | --- |
| `app/Http/Controllers` | Lapas un API (sacensības, scoring, treniņi, trases, vingrinājumi, admins) |
| `app/Models` | Eloquent modeļi un biznesa noteikumi (piem., `Competition::syncLifecycle`) |
| `app/Support/RatingEngine.php` | Raundu, trašu un spēlētāju reitingi |
| `app/Support/CompetitionGrouping.php` | Grupu izloze un sacensību bedrītes |
| `app/Support/CourseNamer.php` | Trašu filtrēšana un nosaukumu atrašana |
| `routes/console.php` | Artisan komandas un scheduler |
| `resources/views/partials/scorekeeper.blade.php` | Rezultātu ievades lietotne (kopīga treniņiem un sacensībām) |
| `tests/` | Feature un Unit testi |
