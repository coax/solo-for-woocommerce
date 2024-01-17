<img src="https://github.com/coax/solo-for-woocommerce/assets/189817/9abf2b62-d5ea-4c94-89ca-f7844ab0a0df" width="120" align="left">

**Solo for WooCommerce** je besplatni dodatak na postojeći [servis Solo](https://solo.com.hr). Njegova funkcionalnost je automatsko kreiranje računa i ponuda za narudžbe koje primiš u svojoj WooCommerce (WordPress) e-trgovini.

\
Preuzmi [najnoviju verziju dodatka](https://github.com/coax/solo-for-woocommerce/releases).

##

### Preduvjeti:
- aktivan Solo korisnički račun (dodatak radi s besplatnim i plaćenim paketom)
- WooCommerce dodatak za WordPress (verzija 5+)

### Mogućnosti:
- automatsko kreiranje računa ili ponude (u servisu Solo) za svaku narudžbu\
<sup>dodatak će proslijediti detalje narudžbe u Solo i na temelju tih podataka kreirati račun ili ponudu</sup>
- detekcija uključenih načina plaćanja u WooCommerce dodatku\
<sup>dodatak će automatski prepoznati i prikazati sve načine plaćanja koje imaš uključene u WooCommerce dodatku</sup>
- biranje željenog tipa dokumenta i trenutka kreiranja za svaki od načina plaćanja\
<sup>za svaki od prikazanih načina plaćanja možeš odabrati željeni tip dokumenta koji će Solo kreirati, kao i trenutak kreiranja (npr. checkout)</sup>
- automatsko slanje računa ili ponude na e-mail kupca\
<sup>možeš upisati željeni naslov i sadržaj poruke s PDF dokumentom koji će biti poslan kupcu, a automatsko slanje možeš i isključiti</sup>
- prikaz polja za unos naziva tvrtke i OIB-a u košarici (za R1 račun)\
<sup>aktivacijom dodatka se automatski dodaju polja za unos naziva tvrtke i OIB-a u košarici</sup>
- dnevna tečajna lista od HNB-a\
<sup>dodatak se automatski brine za ažurnu tečajnu listu, korisno ako prodaješ u stranim valutama (npr. USD)</sup>
- detaljna arhiva narudžbi proslijeđenih u Solo\
<sup>sve narudžbe koje su poslane u Solo će biti izlistane u zasebnoj tablici, korisno za evidenciju i otklanjanje problema</sup>
- automatska fiskalizacija za važeće tipove plaćanja\
<sup>Solo automatski fiskalizira račune s načinom plaćanja kartice ili čekovi tako da se ne moraš brinuti za to</sup>

### Načini plaćanja:
Dodatak automatski prepoznaje načine plaćanja u WooCommerce (ako su dostupni/omogućeni): Direct bank transfer, Check payments, Cash on delivery, Stripe (Credit Card), Stripe (SEPA Direct Debit), PayPal, CorvusPay (Credit Card), Braintree (Credit Card), Braintree (PayPal), Monri (Credit Card).

Pri kreiranju dokumenta u Solo, dodatak će automatski poslati informacije o načinu plaćanja i fiskalizaciji (npr. Direct bank transfer = Transakcijski račun koji se ne fiskalizira, Stripe Credit Card = Kartice i fiskalizira se).

_Bitno: ako tvoja trgovina ne koristi jednu od [podržanih valuta](https://solo.com.hr/api-dokumentacija/valute) ili gore navedenih načina plaćanja, narudžba neće biti poslana u servis Solo._

### Instalacija:
Preuzmi zadnju verziju dodatka i prebaci je u svoj WordPress (u mapu /wp-content/plugins) te aktiviraj iz administracije.\
Nakon aktiviranja dodatka, sve upute će ti biti prikazane na ekranu.

https://github.com/coax/solo-for-woocommerce/assets/189817/c7faf437-517c-4804-b7d9-f4c2bfd5a1a7

### Podrška:
Ovaj dodatak je u razvojnoj fazi i moguće su greške pri korištenju. To je normalno za očekivati jer ne postoji idealan scenarij u moru WordPress instalacija i dodataka (pluginova). Na besprijekoran rad ovog dodatka mogu utjecati drugi dodaci koje imaš instalirane.

Za tehničku podršku molimo te da koristiš [GitHub Issues](https://github.com/coax/solo-for-woocommerce/issues). Slobodno nam piši na hrvatskom jeziku.

### Daljnji razvoj:
Solo je kreirao dodatak s idejom jednostavnosti korištenja i izbacivanjem nepotrebnih opcija. Bugove ćemo potamaniti u što kraćem roku (ako ih prijaviš), a slobodno baci pogled na izvorni kod i uključi se s optimizacijama i doradama za koje misliš da bi bile korisne svima.

_Javi nam se ako želiš da dodamo nove načine plaćanja (primarno hrvatske payment providere)._

### Odricanje odgovornosti:
Nemoj nas zahejtati ako plugin ne radi baš na tvojoj WordPress stranici. Testirali smo dodatak u mogućnostima koje smo imali, pa ako baš tebi zadaje probleme, žao nam je i pokušati ćemo pomoći kada nas kontaktiraš.

Pravni tekst: Korisniku se pruža najam ove usluge u konačnom obliku, po principu "kakav jest" (eng. "as is") te se isključuje bilo kakvo jamstvo glede bilo kakvih nedostataka ove usluge. Iako autor dodatka radi kontinuirano testiranje i održavanje ove usluge, ne može jamčiti da prilikom korištenja neće doći do pogrešaka u radu same usluge te da tom prilikom neće doći do eventualnih kvarova.
U svakom slučaju, autor dodatka će poduzeti sve korake da kvar otkloni u najkraćem mogućem roku.\
Autor dodatka ne odgovara korisniku usluge za štetu ukoliko dođe do gubitka ili uništenja podataka, nedopuštenog pristupa, nedopuštene promjene, nedopuštenog objavljivanja ili bilo koje druge zlouporabe, a posebno kada su navedene okolnosti uzrokovane višom silom, kvarom opreme, pogrešnim rukovanjem, utjecajem drugih licenciranih i nelicenciranih računalnih programa, virusa i ostalih štetnih utjecaja.
