# Changelog - plg_system_fgadminlogincustom

## 1.17.0 (2026-08-04)
- PHP namespace a názov triedy zjednotené s konvenciou FG série rozšírení (podľa plg_system_fgstripcomments): `Fero\Plugin\System\FgAdminLoginCustom\Extension\FgAdminLoginCustom` → `FG\Plugin\System\AdminLoginCustom\Extension\AdminLoginCustom`. Súbor triedy premenovaný na `src/Extension/AdminLoginCustom.php`.
- Joomla `element` (`fgadminlogincustom`), názov priečinka, jazykové kľúče aj displejový názov ostávajú nezmenené - z pohľadu Joomly ide o štandardný update, nie o nový plugin. Žiadna funkčná zmena.
- Doplnené `<authorUrl>` a `<updateservers>` do manifestu - Joomla bude o nových verziách informovať priamo z GitHub repozitára (`updates.xml`).
- Pridané súbory pre GitHub publikáciu: `README.md`, `LICENSE` (GPL-2.0), `.gitignore`, `updates.xml`.
- Overené kompletnou testovacou sadou po refaktore (buildCss, export/import, presety, custom JS, tiene, kontrast) - všetko funguje nezmenene.

## 1.16.5 (2026-08-04)
- Displejový názov pluginu v zozname pluginov zjednotený s novou identitou: „System - Admin Login Customizer" → „System - FG Admin Login Customizer" (en-GB), „Systém - Úprava admin prihlásenia" → „Systém - FG Úprava admin prihlásenia" (sk-SK). Zmenené v `.ini` aj `.sys.ini` v oboch jazykoch (sys.ini ovplyvňuje názov v zozname Extensions ešte pred inštaláciou/pri objavení).


## 1.16.4 (2026-08-03)
- Potvrdené priamo na postihnutom webe (mechanizmysevcik.sk): oprava jazykového reťazca v 1.16.3 (odstránenie doslovného `<script>` textu) obnovila funkčnosť Save/Save & Close/Close tlačidiel.
- `onContentPrepareForm` (Export/Import/Preset auto-vypĺňanie polí) OPÄŤ ZAPNUTÝ - bola to false stopa, skutočná príčina bola v jazykovom reťazci, nie v tejto logike (viď 1.16.2/1.16.3 pre plné vyšetrovanie).
- Funkčnosť je teraz zhodná s v1.16.1 (mutácia `$data->params` so zachovaním typu, `try/catch` poistka), navyše bez problémovej `<script>` frázy v popise.


## 1.16.3 (2026-08-03) - PRAVDEPODOBNÁ SKUTOČNÁ PRÍČINA NÁJDENÁ
- Bisectovaním potvrdené: v1.15.1 fungovalo, v1.16.0 (pridanie poľa „Custom JavaScript") pokazilo Save/Close. Keďže problém pretrvával aj s úplne vypnutou PHP logikou (v1.16.2), príčina musela byť v STATICKEJ XML/jazykovej deklarácii, nie v runtime kóde.
- NÁJDENÉ: jazykový reťazec `PLG_SYSTEM_FGADMINLOGINCUSTOM_CUSTOM_JS_DESC` (popis poľa, zobrazovaný ako tooltip) obsahoval doslovný text `<script>` ("No <script> tags..."). Joomla admin stránky vkladajú texty popisov polí do HTML/JS kontextu spôsobom, ktorý túto sekvenciu pravdepodobne neescapuje spoľahlivo - výsledkom bolo poškodenie HTML štruktúry celého formulára (chýbajúci `task` input → `Uncaught TypeError: Cannot set properties of undefined` pri Save/Close).
- OPRAVA: popis prefrázovaný bez doslovných uhlových zátvoriek ("Do not wrap it in a script element..." / "Nezaobaľuj ho do elementu script...").
- Kompletne prehľadané všetky ostatné jazykové reťazce a XML popisy/labely na podobný vzor - žiadny ďalší výskyt nenájdený.
- `onContentPrepareForm` (Export/Import/Preset) OSTÁVA ZATIAĽ VYPNUTÝ z v1.16.2 - zámerne, aby sa táto oprava overila izolovane ako jediná zmena, než sa pridá späť ďalšia vrstva.


## 1.16.2 (2026-08-03) - STABILIZAČNÁ VERZIA
- KRITICKÉ: Na mechanizmysevcik.sk hlásená rozbitá edit obrazovka pluginu - tlačidlá Save/Save & Close/Close nefungovali, chýbala posledná záložka. JS konzola ukázala `Uncaught TypeError: Cannot set properties of undefined (setting 'value') at Joomla.submitform` - symptóm poškodenej HTML štruktúry formulára (chýbajúci skrytý `task` input).
- Podozrenie: `export_json` pole vkladalo do stránky veľký, dynamicky poskladaný JSON blok (vrátane hodnôt ako `footer_text` s HTML odkazmi) - toto pravdepodobne pri vykresľovaní `readonly` textarea poľa prelomilo HTML štruktúru formulára.
- OKAMŽITÁ NÁPRAVA (stabilizácia): `onContentPrepareForm` subscription (a s ňou celá logika Export/Import/Preset auto-vypĺňania) je DOČASNE ÚPLNE VYPNUTÁ. Toto garantovane obnoví funkčnosť Save/Close/Apply, keďže Joomla teraz vykresľuje polia štandardným spôsobom bez akéhokoľvek nášho zásahu.
- DÔSLEDOK: Záložka „Preset" a „Export / Import" v tejto verzii NEFUNGUJE (dropdown/textarea polia sú viditeľné, ale výber presetu ani vloženie JSON nič neaplikuje). Ostatné funkcie (logo, pozadie, farby, tiene, custom CSS/JS na login stránke) sú plne funkčné a touto zmenou nedotknuté.
- Táto verzia je zámerne konzervatívna po dvoch neúspešných pokusoch o opravu na diaľku - cieľom je najprv obnoviť stabilitu, Export/Import/Preset bude dôkladne prerobené a znovu otestované priamo proti postihnutému prostrediu v samostatnej nasledujúcej verzii.


## 1.16.1 (2026-08-03)
- OPRAVA (kritická): Save/Save & Close/Close tlačidlá na edit obrazovke pluginu prestali fungovať a záložka Export/Import zmizla. Príčina: `applyValuesToData()` obsahovala poistku, ktorá pri `$data->params` inom type než pole/objekt (typicky **raw JSON string** - takto ho vracia `com_plugins` model PRED tým, než Joomla zavolá `$form->bind()`) tento string **nahradila prázdnym poľom**, čím zničila všetky pôvodné dáta ešte pred spracovaním formulára. To s najväčšou pravdepodobnosťou spôsobilo fatálnu chybu neskôr v Joomla `bind()` kroku, čo poškodilo vykreslenie stránky (vrátane JS, ktorý ovláda toolbar tlačidlá).
- Náprava: `mergeIntoParams()` teraz **zachováva pôvodný typ** dát - string ostane string (dekóduje sa, zmerguje, zas zakóduje naspäť), Registry/array/objekt sa mení na mieste ako doteraz. Nový/neznámy, nie prázdny typ sa už nikdy neprepíše - metóda v takom prípade radšej nič nezmení (vráti `null`, volajúci pôvodné dáta ponechá netknuté), než aby hádala a niečo zničila.
- Čítanie parametrov pre Export teraz tiež správne spracúva `$data->params` ako string (predtým padalo do vetvy "neznámy typ -> prázdne pole", čo mohlo spôsobovať aj prázdny/nefunkčný Export sám osebe).
- Celá `prepareAdminForm()` je navyše obalená do `try/catch` ako poistka - akákoľvek nepredvídaná výnimka sa už nikdy neprejaví navonok poškodením stránky, len sa daný beh ticho preskočí.
- Overené 8 novými testami vrátane presne podozrivého scenára (`$data->params` ako JSON string, prázdny string, aj úplne neznámy typ ako poistka) + opätovné spustenie všetkých predchádzajúcich regresných testov.


## 1.16.0 (2026-08-03)
- Nové pole „Vlastný JavaScript" v sekcii Advanced, hneď za „Vlastné CSS" - posledných 5% pre prípady, čo CSS nevyrieši.
- JS sa vkladá cez `WebAssetManager::addInlineScript()` s `defer`, iba na admin login stránke (rovnaký `isLoginPage()` guard ako CSS a logo/footer injekcia).
- Rovnaký dôveryhodnostný model ako Vlastné CSS a Footer text (admin-only, `filter="raw"`) - žiadna nová kategória rizika, len rozšírenie existujúcej dôvery voči obsahu, ktorý do pluginu zadáva samotný administrátor.
- Export/Import a Presety zahŕňajú `custom_js` automaticky, bez akejkoľvek dodatočnej logiky - mechanizmus je generický voči všetkým parametrovým kľúčom.
- Overené 4 testami: injekcia pri vyplnenom poli, žiadna injekcia pri prázdnom, súbežná funkčnosť CSS+JS, automatické zahrnutie do exportu.


## 1.15.1 (2026-08-03)
- OPRAVA: Export/Import a Preset polia sa v reálnej Joomla neaktualizovali, hoci systémová správa hlásila úspech. Príčina: `Form::setValue()` mení iba dočasnú kópiu poľa vo `$form` objekte, ale `onContentPrepareForm` sa spúšťa PRED tým, než Joomla naviaže `$data` na formulár (`$form->bind($data)`) - toto naviazanie naše `setValue()` volania ticho prepísalo späť na pôvodné uložené hodnoty.
- Náprava: hodnoty sa teraz zapisujú priamo do `$data->params` (mutáciou Registry/array/objektu na mieste) - presne tak, ako to popisuje oficiálna Joomla dokumentácia k tomuto eventu ("If you set any of these properties then they will be modified in the form data which is presented to the user"). Nová metóda `applyValuesToData()` nahrádza pôvodnú `applyValuesToForm()`.
- Overené end-to-end testami simulujúcimi reálny tok: export/import/preset hodnoty sú teraz overiteľne prítomné priamo v `$data->params` objekte po volaní `prepareAdminForm()`, nie iba vo `$form`.


## 1.15.0 (2026-08-03)
- REBRAND: úplné premenovanie na `fgadminlogincustom` - nový Joomla `element`, nový priečinok (`plg_system_fgadminlogincustom`), nový hlavný XML súbor, nový PHP namespace/trieda (`Fero\Plugin\System\FgAdminLoginCustom\Extension\FgAdminLoginCustom`), nové jazykové kľúče (`PLG_SYSTEM_FGADMINLOGINCUSTOM_*`) a nové názvy jazykových súborov. Z pohľadu Joomly ide o úplne nový, samostatný plugin - nie update pôvodného `adminlogincustom` (ten zostáva nainštalovaný nezávisle, ak bol predtým nasadený; táto verzia ho neaktualizuje ani neodstraňuje).
- `<author>` nastavený na „Fero".
- Číslovanie verzie pokračuje z 1.14.0 pôvodného pluginu (nezačína sa odznova od 1.0.0), CHANGELOG história nižšie (do v1.14.0 vrátane) dokumentuje vývoj pod pôvodným názvom `adminlogincustom` a je ponechaná bezo zmeny.
- Žiadna funkčná zmena oproti v1.14.0 - všetky parametre, presety, export/import aj CSS logika sú identické, zmenila sa iba identita rozšírenia.

## 1.14.0 (2026-08-03)
- Nový parameter „Tieň karty" (None/Subtle/Medium/Strong) v sekcii „Prihlasovacia karta a farby", hneď za „Zaoblenie karty".
- Predvolená hodnota „None" = žiadna zmena oproti predchádzajúcim verziám (plná spätná kompatibilita).
- Fixné box-shadow presety (rastúca hĺbka): Subtle `0 1px 3px rgba(0,0,0,.15)`, Medium `0 4px 12px rgba(0,0,0,.18)`, Strong `0 12px 32px rgba(0,0,0,.28)`.
- Overené 7 testami: spätná kompatibilita (chýbajúci aj explicitný "none"), všetky 3 úrovne tieňa, bezpečné správanie pri neplatnej hodnote, kombinácia s ostatnými vlastnosťami karty.


## 1.13.0 (2026-08-03)
- Automatický výpočet kontrastnej farby textu (inšpirované konkurenčným pluginom Admin Customizer od Joomill): ak je nastavená farba pozadia (`card_bg`, `btn_bg`, `header_bg`) a zodpovedajúce pole farby textu (`card_text`, `btn_text`, `header_text`) je prázdne, namiesto ponechania bez štýlu sa teraz automaticky dopočíta čitateľná čierna/biela farba textu podľa WCAG relatívnej luminancie (porovnáva sa reálny kontrastný pomer voči čiernej aj bielej, vyberie sa vyšší, nie len jednoduchý prah jasu).
- Rieši reálne riziko nečitateľnosti: doteraz pri nastavení tmavého vlastného pozadia a zabudnutí nastaviť farbu textu ostal predvolený (často tmavý) text šablóny Atum - potenciálne nečitateľný. Teraz sa automaticky prepne na bielu.
- Nová zdieľaná metóda `autoContrastColor()`.
- 100% spätne kompatibilné: ak text farbu vyplníš explicitne, má vždy prednosť pred automatickým výpočtom - žiadna zmena správania pre existujúce nastavenia s vyplnenými farbami. Ak nie je nastavené ani pozadie, správanie je nezmenené (žiadne CSS pravidlo, farba šablóny).
- Overené 13 automatizovanými testami: presnosť WCAG algoritmu na 6 referenčných farbách, spätná kompatibilita, priorita explicitnej voľby, integrácia do všetkých troch miest (karta/tlačidlá/header) aj celého `buildCss()` behu.


## 1.12.0 (2026-07-24)
- Nová sekcia „Preddefinovaná téma" (prvá záložka) s dropdownom „Aplikovať preset": Default (reset na farby šablóny Atum), Dark, Corporate Blue, Green, Orange, Minimal.
- Rovnaký dvojkrokový vzor ako Import z v1.11.0: vyber preset → Save (predvyplní farebné polia v celom formulári, systémová správa to potvrdí) → skontroluj → Save ešte raz na uloženie.
- Presety menia LEN farebné/pozadiové polia (bg_type, bg_color/grad_*, card_*, btn_*, link_color, header_bg/text) - nikdy sa nedotknú layoutových/súkromia prepínačov (hide_sidebar, hide_chrome, hide_forgot, hide_template_logo, hide_generator, hide_lang_switcher), takže aplikovanie presetu nikdy potichu nezmení štrukturálne nastavenia.
- REFAKTOR: zdieľaná metóda `applyValuesToForm()` extrahovaná pre import aj presety - žiadna duplicitná logika.
- Vedomé obmedzenie rozsahu (voči pôvodnému návrhu 9 tém): „Glass", „Material", „Bootstrap", „Modern" vynechané - vyžadovali by nové CSS vlastnosti mimo súčasného farebného modelu (backdrop-filter blur, box-shadow elevation), nie len iné farby.
- Overené 5 testami: aplikovanie presetu, reset na prázdne, bezpečné tiché zlyhanie pri neznámom kľúči presetu, žiadna akcia pri prázdnej hodnote, layout prepínače ostávajú nedotknuté.


## 1.11.0 (2026-07-24)
- Nová záložka „Export / Import" v nastaveniach pluginu.
- „Export (aktuálne nastavenia)" - read-only pole, vždy zobrazuje aktuálne uložené parametre ako formátovaný JSON. Skopírovaním sa dá preniesť celý dizajn na iný web (napr. medzi fnspza.sk, khanovaskola.sk, mechanizmysevcik.sk, urbarterchova.sk).
- „Import" - vlož JSON exportovaný z iného webu, klikni Save. Polia sa na tej istej obrazovke predvyplnia importovanými hodnotami (systémová správa to potvrdí) - skontroluj ich a klikni Save ešte raz, aby sa reálne uložili. Pole sa po použití samo vyprázdni.
- ZÁMERNÝ DIZAJN - dvojkrokový import namiesto jednokrokového: `onExtensionBeforeSave` (potrebný pre okamžitú aplikáciu pri prvom Save) má zdokumentovanú históriu nefunkčnosti špecificky pri ukladaní pluginov cez com_plugins (Joomla core issue #7529, oprava #41175 cielená na [6.1], nie isté či je súčasťou aktuálnej minor verzie). Namiesto stavby na tomto riziku použitý spoľahlivý `onContentPrepareForm` (žiadna podobná história), ktorý sa spustí pri každom znovunačítaní edit formulára - čo tlačidlo „Save" (bez Close) prirodzene robí.
- Neplatný JSON v poli Import sa bezpečne ignoruje s varovnou systémovou správou, pole sa napriek tomu vyčistí.
- Overené 5 automatizovanými testami: export obsahuje správne dáta a vylučuje meta-kľúče, izolácia od iných pluginov/formulárov, korektná aplikácia importu vrátane vyčistenia poľa, bezpečné zlyhanie pri neplatnom JSON.


## 1.10.0 (2026-07-24)
- Nové parametre v sekcii „Logo a rozloženie": „Pevná výška loga (px)" a „Režim vyplnenia (Fit)" (Contain/Cover).
- Predvolené správanie (pole výšky prázdne) je nezmenené - logo sa škáluje voľne podľa `Logo max width`, pomer strán sa vždy zachová, žiadne skreslenie. Toto je 100% spätne kompatibilné s predchádzajúcimi verziami.
- Ak sa vyplní „Pevná výška loga", obrázok dostane pevný box `šírka × výška` a `object-fit` (contain = celé logo viditeľné, môže ostať prázdny priestor; cover = box vyplnený celý, logo môže byť orezané) rozhodne, ako sa doň logo vmestí. Užitočné najmä pri viacerých weboch s logami rôznych pomerov strán, kde treba konzistentnú veľkosť boxu.
- Neplatná/chýbajúca hodnota `logo_fit` bezpečne padá na `contain`.
- Overené testami: spätná kompatibilita (výstup bez zadanej výšky je bit-identický s v1.9.2) aj nová vetva s pevným boxom a rôznymi fit režimami.


## 1.9.2 (2026-07-24)
- OPRAVA ROBUSTNOSTI: nahradený krehký non-greedy regex (`.*?</div>`) pre vloženie loga do `.main-brand.logo` slotu novou metódou `replaceBalancedDivContent()` - depth-counting scanner, ktorý korektne nájde zodpovedajúci uzatvárací `</div>` aj keby slot v budúcej verzii Atum šablóny obsahoval vnorené divy (kde by pôvodný `.*?` regex zastavil na prvom, nesprávnom `</div>` a rozbil HTML).
- Zvážený, ale zámerne NEPOUŽITÝ prístup cez plnohodnotný `DOMDocument`/`DOMXPath` na celý `$body` - re-serializácia celej stránky by riskovala vedľajšie poškodenie inline JSON v debug bare a SVG passkey ikony, čo je väčšie riziko než pôvodný problém. Balanced-tag scanner rieši presne ten istý problém (krehkosť voči vnoreným divom) bez tohto vedľajšieho rizika, keďže mení iba presne ohraničený úsek reťazca.
- Overené testami: reálny produkčný HTML výstup (logo, footer, sidebar, balancované značky) aj syntetický scenár s vnoreným divom vo vnútri loga - v oboch prípadoch korektný výsledok.

## 1.9.1 (2026-07-24)
- REFAKTOR: `buildCss()` (predtým ~230 riadkov v jednej metóde) rozdelená na 9 malých, jednoúčelových metód: `buildBackgroundCss()`, `buildChromeCss()`, `buildSidebarCss()`, `buildHeaderBarCss()`, `buildLoginCardCss()`, `buildButtonCss()`, `buildLogoCss()`, `buildFooterCss()`, `buildCustomCss()` + pomocná `resolveButtonColors()` (farby tlačidla sa počítajú raz a zdieľajú s header bar sekciou pre `header_match_btn`).
- `buildCss()` teraz len skladá výsledky jednotlivých metód cez `array_merge` a `implode`.
- Žiadna funkčná zmena: overené automatizovaným porovnaním výstupu starej (monolitickej) a novej (rozdelenej) implementácie pre 5 rôznych kombinácií parametrov (default, plne nastavené farby, gradient, obrázok pozadia, žiadne farby) - výstupný CSS je identický.

## 1.9.0 (2026-07-24)
- Polia „Custom logo" a „Obrázok pozadia" vrátené naspäť na type="media" (štandardný Joomla Media Manager browse dialóg), tak ako vo v1.5.0. Všetky ostatné funkcie pridané od v1.6.0 (Horný pás, zladenie farieb tlačidiel a pod.) ostávajú zachované.
- Poznámka: ak sa Media Manager na danom serveri/prehliadači správa nespoľahlivo (pozri riešenie v predošlej session - problém bol viazaný na konkrétny Chrome profil, nie na hosting ani na plugin), hodnotu poľa je možné aj naďalej zadať ručne priamo do textového vstupu vedľa náhľadu obrázka - Joomla `media` field to umožňuje bez ohľadu na stav Media Manager dialógu.

## 1.8.1 (2026-07-24)
- Tlačidlo „Sign in with a passkey" (.btn-secondary) teraz preberá rovnaké farby (pozadie/hover/text) ako login tlačidlo (.btn-primary) cez existujúce parametre Button background/hover/text - žiadny nový parameter, len rozšírenie existujúcich selektorov.

## 1.8.0 (2026-07-24)
- Nový parameter „Zladiť tlačidlo frontend odkazu s login tlačidlom" v sekcii „Horný pás": tlačidlo s odkazom na frontend (napr. „Pôrodnica Žilina") v hornom páse prevezme rovnaké farby pozadia/hover/textu ako login tlačidlo (parametre Button background/hover/text zo sekcie „Prihlasovacia karta a farby") - žiadna duplicitná farba, len prepínač.
- Selektor cielene zasahuje iba `a.header-item-content` (samotný odkaz), takže sa netýka jazykového prepínača, ktorý zdieľa triedu `.header-item-content`, ale je na `<div>`, nie `<a>`.


## 1.7.0 (2026-07-24)
- Nová záložka „Horný pás": farba pozadia (`header_bg`), farba textu/odkazov/ikon (`header_text`) a prepínač „Skryť jazykový prepínač" (`hide_lang_switcher`) pre header bar (#header) na login stránke.
- Header bar štýlovanie je nezávislé od „Skryť hlavičku a pätičku" - ak je hlavička skrytá, tieto pravidlá jednoducho nemajú čo štýlovať.


## 1.6.0 (2026-07-24)
- ZMENA: Polia „Custom logo" a „Obrázok pozadia" prepnuté z type="media" na type="text" (obyčajné textové pole s ručným zadaním relatívnej cesty, napr. images/logo.png). Dôvod: na niektorých hostingoch (typicky jailované shared hostingy s obmedzeným open_basedir alebo symlinkom na priečinok images) je natívny Joomla Media Manager (com_media) nefunkčný - nezobrazí súbory a validácia hodnoty médií pri ukladaní zlyháva bez ohľadu na príponu súboru. Textové pole tento problém úplne obchádza, keďže neprechádza cez com_media validáciu.
- Popisy polí upravené s inštrukciou k formátu cesty (relatívne od koreňa webu, bez úvodného lomítka).
- cleanMediaUrl() zostáva nezmenená - funguje rovnako správne aj pre ručne zadané relatívne aj absolútne URL.


## 1.5.0 (2026-07-18)
- Nový parameter „Text pod formulárom": voliteľný text/HTML (napr. „Created by ...") vložený server-side hneď za </form> - presne tam, kde bol pôvodne odkaz „Forgot your login details?". Podporuje HTML vrátane odkazov (filter raw, admin-only nastavenie).
- Footer má vlastnú triedu .alc-footer (zámerne bez .text-center, aby ho nezasiahlo pravidlo hide_forgot) s decentným štýlom: centrovaný, menšie písmo, opacity 0.85.
- Bezpečné escapovanie $ a \ v replacement reťazci (žiadne backreference side-effecty z admin HTML).
- Refaktor: replaceLogo() premenovaná na modifyBody(), obsluhuje logo aj footer v jednom onAfterRender prechode.


## 1.4.2 (2026-07-18)
- Obrázok pozadia: odstránený `background-attachment: fixed` (na iOS Safari spôsobuje rozbité/zväčšené renderovanie; login stránka sa aj tak neskroluje). Pozadie zostáva `center center / cover no-repeat` - vycentrované a prispôsobené veľkosti obrazovky.
- Pridaný `min-height: 100vh` na body pri vlastnom pozadí, aby pokrývalo vždy celý viewport.


## 1.4.1 (2026-07-18)
- OPRAVA: Selektor `.login > .text-center` z v1.4.0 skrýval aj slot s logom (`main-brand logo text-center` je tiež priamy potomok `.login`). Nahradené za `.login form ~ .text-center` - skryje sa iba blok s odkazom na obnovu hesla ZA formulárom; logo pred formulárom nemôže byť zasiahnuté.


## 1.4.0 (2026-07-18)
- Nový parameter „Skryť odkaz 'Forgot your login details?'" (predvolene zapnutý): skryje blok `.login > .text-center` s odkazom na obnovu hesla pod formulárom, ktorý smeruje na guide.joomla.org a prezrádza CMS.


## 1.3.0 (2026-07-18)
- ZMENA: Logo sa už nevkladá JavaScriptom, ale server-side v `onAfterRender` - obsah natívneho slotu `.main-brand.logo` sa nahradí vlastným logom priamo v HTML pred odoslaním do prehliadača. Odstránený "preblik" (veľké logo šablóny -> zmenšenie po DOMContentLoaded): správny obrázok v správnej veľkosti je tam od prvého vykreslenia, žiadny layout shift.
- Regex cielene matchuje iba div s triedami `main-brand` + `logo` (slot nad formulárom); sidebarový `#main-brand` bez triedy `logo` zostáva nedotknutý. Fallback: ak slot neexistuje, logo sa vloží ako prvý element `.login` boxu. Overené na reálnom HTML z Joomla 6.1.2 / Atum.
- Odstránená JS injekcia (buildLogoScript), CSS sizing sa aplikuje na `.alc-logo-img` aj `.main-brand img` okamžite.
- Refaktor: podmienky login stránky zjednotené do isLoginPage().


## 1.2.1 (2026-07-18)
- Logo je vycentrované za každých okolností: kontajner `.alc-logo` (aj natívny slot `.login .main-brand`) prepnutý na flex s `justify-content: center`, `width: 100%`, `margin: 0 auto` a `float: none` (všetko s !important). Obrázok má `display: block`, `margin: 0 auto`, `width/height: auto` s max-width podľa parametra - žiadne rozťahovanie ani upscale malých log.


## 1.2.0 (2026-07-18)
- OPRAVA: Vlastné logo sa nezobrazovalo - Atum login stránka nemá `.card` (biely box je `<div class="login">`). JS teraz vkladá logo priamo do natívneho Atum slotu `.login .main-brand` nad formulárom (s fallbackom na `.login` / `.card`).
- OPRAVA: Selektory `hide_template_logo` a `hide_sidebar` boli príliš generické (`.view-login .logo`, `.main-brand`) a skrývali aj natívny login-logo slot nad formulárom. Zúžené: header logo len cez `#header .logo`, sidebar len cez `#sidebar-wrapper` (markup overený voči joomla-cms master: atum/login.php, mod_login/tmpl/default.php).
- Štýlovanie karty (pozadie/text/radius) sa teraz aplikuje aj na `.login` box, nie iba `.card`.


## 1.1.0 (2026-07-18)
- Nový parameter „Skryť ľavý panel" (predvolene zapnutý): skryje celý ľavý sidebar login stránky - panel s logom šablóny aj modul „Need Support?" s Joomla odkazmi; obsah sa roztiahne na plnú šírku.
- Nový parameter „Odstrániť generator meta tag" (predvolene zapnutý): na login stránke odstráni meta generator prezrádzajúci Joomlu v zdrojovom kóde.


## 1.0.0 (2026-07-18)
- Prvé vydanie (Joomla 6 native: PSR-4, SubscriberInterface, DI cez services/provider.php; kompatibilné aj s J4/J5).
- Vlastné logo nad prihlasovacou kartou (media field, nastaviteľná max. šírka), voliteľné skrytie loga šablóny a Joomla ikony (.login-joomla).
- Pozadie: plná farba / lineárny gradient (uhol) / obrázok s farebným overlay a nastaviteľnou priehľadnosťou.
- Štýlovanie prihlasovacej karty: pozadie, farba textu, border-radius; farby tlačidla (bg/hover/text) a odkazov.
- Voliteľné skrytie hlavičky a pätičky (fullscreen login).
- Pole na vlastné CSS (aplikuje sa ako posledné, iba na login stránke).
- Injekcia cez WebAssetManager v onBeforeCompileHead, iba pre guest používateľa a option prázdny/com_login.
