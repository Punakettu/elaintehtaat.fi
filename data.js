// data.js — placeholder content for the Eläintehtaat media archive.
// Three-level hierarchy: Project → Album (primary unit) → Media.
// Realistic Finnish titles/metadata so layouts read as real. Locations are
// coarse by default (municipality / region); precise coords are rare & opt-in.

(function () {
  const PHOTOGRAPHERS = [
    'Anni Vuori', 'Mikko Lahtinen', 'Sanna Korhonen',
    'Eero Niemelä', 'Laura Mäkinen', 'Tuomas Heikkilä',
  ];

  const LICENSES = {
    byncnd: { code: 'CC BY-NC-ND 4.0', fi: 'Ei-kaupallinen, ei muokkauksia', en: 'Non-commercial, no derivatives' },
    bync:   { code: 'CC BY-NC 4.0',    fi: 'Ei-kaupallinen',                 en: 'Non-commercial' },
    edit:   { code: 'Toimituksellinen', fi: 'Vain toimituksellinen käyttö',  en: 'Editorial use only' },
  };

  // Deterministic pseudo-random so tile tones are stable across reloads.
  function rng(seed) {
    let s = seed % 2147483647;
    if (s <= 0) s += 2147483646;
    return () => (s = (s * 16807) % 2147483647) / 2147483647;
  }

  // Build a photographic-feeling tone for a placeholder tile.
  // Dark gallery: mid-dark lightness, very low chroma, varied hue.
  function tone(seed) {
    const r = rng(seed * 97 + 13);
    const hue = Math.round(30 + r() * 250);
    const l = 0.30 + r() * 0.22;          // base lightness
    const c = 0.006 + r() * 0.022;        // subtle chroma
    const ang = Math.round(120 + r() * 70);
    const c1 = `oklch(${(l - 0.10).toFixed(3)} ${c.toFixed(3)} ${hue})`;
    const c2 = `oklch(${(l + 0.07).toFixed(3)} ${(c * 0.7).toFixed(3)} ${hue})`;
    return { hue, ang, c1, c2 };
  }

  const fmtDate = (iso) => iso; // kept ISO; formatted in UI per locale

  // Media generator for an album.
  function makeMedia(album, specs) {
    return specs.map((spec, i) => {
      const id = `${album.id}-m${i + 1}`;
      const seed = (album.seed || 1) * 31 + i * 7 + 3;
      return {
        id,
        albumId: album.id,
        type: spec.v ? 'video' : 'image',
        duration: spec.v || null,
        tone: tone(seed),
        captionFi: spec.fi || null,
        captionEn: spec.en || null,
        captureDate: spec.d || album.date,
        photographer: spec.p || album.photographer,
        license: album.license,
        span: spec.span || null, // featured-grid sizing hint
      };
    });
  }

  // ── Projects ────────────────────────────────────────────────────────────
  const projects = [
    { id: 'p-sika22', fi: 'Sikatilat 2022', en: 'Pig farms 2022', year: 2022,
      blurbFi: 'Kolmen maakunnan lihasikaloiden olosuhteet dokumentoituna kevään ja kesän 2022 aikana.',
      blurbEn: 'Conditions in meat-pig facilities across three regions, documented spring–summer 2022.' },
    { id: 'p-turkis', fi: 'Turkistarhaus 2023', en: 'Fur farming 2023', year: 2023,
      blurbFi: 'Kettu- ja minkkitarhojen häkkiolosuhteet Pohjanmaan tuotantoalueella.',
      blurbEn: 'Cage conditions on fox and mink farms across the Ostrobothnia production belt.' },
    { id: 'p-broiler', fi: 'Broileriteollisuus', en: 'Broiler industry', year: 2024,
      blurbFi: 'Nopeakasvuisten lihasiipikarjojen kasvatushallit ja teurastusketju.',
      blurbEn: 'Grow-out halls and the slaughter chain of fast-growing meat poultry.' },
    { id: 'p-lypsy', fi: 'Lypsykarja', en: 'Dairy cattle', year: 2023,
      blurbFi: 'Parsi- ja pihattonavetoita sekä vasikoiden erilliskasvatus.',
      blurbEn: 'Tie-stall and loose-housing barns, and the separate rearing of calves.' },
    { id: 'p-kala', fi: 'Kalankasvatus', en: 'Fish farming', year: 2024,
      blurbFi: 'Kirjolohen ja siian allas- ja verkkoallaskasvatus sisävesillä.',
      blurbEn: 'Tank and net-pen farming of rainbow trout and whitefish in inland waters.' },
    { id: 'p-muna', fi: 'Munintakanalat', en: 'Egg-laying hens', year: 2025,
      blurbFi: 'Virikehäkki- ja lattiakanaloiden olosuhteet munantuotannossa.',
      blurbEn: 'Conditions in enriched-cage and floor systems of egg production.' },
  ];

  // ── Albums (the primary descriptive unit) ────────────────────────────────
  const albumDefs = [
    {
      id: 'a-jokioinen', projectId: 'p-sika22', seed: 7,
      fi: 'Lihasikala, Jokioinen', en: 'Meat pig farm, Jokioinen',
      location: 'Jokioinen, Kanta-Häme', region: 'Kanta-Häme',
      date: '2022-05-14', photographer: 'Anni Vuori',
      species: ['Sika'], speciesEn: ['Pig'], use: 'Lihantuotanto', useEn: 'Meat',
      license: LICENSES.byncnd, mediaCount: 14, featured: true,
      coordsOptIn: false,
      descFi: 'Noin 2 200 lihasian kasvattamo Kanta-Hämeessä. Kuvat on otettu kasvatuksen loppuvaiheessa, jolloin eläimet ovat lähellä teurasikää. Karsinat ovat ritiläpohjaisia ja vailla kuiviketta; virikkeenä oli paikoin lyhyt ketju. Useilla eläimillä näkyi hännänpurentaa ja ihovaurioita. Sarja dokumentoi tilan yleisilmeen, karsinaolosuhteet sekä yksittäisten eläinten kunnon.',
      descEn: 'A facility holding roughly 2,200 meat pigs in Kanta-Häme. Photographs were taken in the late fattening stage, with animals close to slaughter weight. Pens are slatted and bare of bedding; enrichment in places amounted to a short chain. Tail-biting and skin lesions were visible on several animals. The set documents the overall site, pen conditions, and the condition of individual animals.',
    },
    {
      id: 'a-loimaa', projectId: 'p-sika22', seed: 11,
      fi: 'Emakkosikala, Loimaa', en: 'Sow unit, Loimaa',
      location: 'Loimaa, Varsinais-Suomi', region: 'Varsinais-Suomi',
      date: '2022-06-02', photographer: 'Mikko Lahtinen',
      species: ['Sika'], speciesEn: ['Pig'], use: 'Lihantuotanto', useEn: 'Meat',
      license: LICENSES.byncnd, mediaCount: 9, featured: true, coordsOptIn: false,
      descFi: 'Emakoiden tiineytys- ja porsitusosasto. Tiineytyshäkit rajoittavat liikkumisen kääntymisen estävään tilaan. Porsitushäkeissä emakot eivät pääse kontaktiin pahnueensa kanssa muuten kuin imetyksen aikana.',
      descEn: 'Gestation and farrowing section for sows. Gestation crates restrict movement to a space that prevents turning. In farrowing crates, sows cannot contact their litter except during nursing.',
    },
    {
      id: 'a-kaustinen', projectId: 'p-turkis', seed: 19,
      fi: 'Turkistarha, Kaustinen', en: 'Fur farm, Kaustinen',
      location: 'Kaustinen, Keski-Pohjanmaa', region: 'Keski-Pohjanmaa',
      date: '2023-07-21', photographer: 'Sanna Korhonen',
      species: ['Kettu'], speciesEn: ['Fox'], use: 'Turkis', useEn: 'Fur',
      license: LICENSES.bync, mediaCount: 12, featured: true, coordsOptIn: false,
      descFi: 'Sinikettujen verkkopohjaisia häkkejä avoimissa varjotaloissa. Useat eläimet ylipainoisia jalostuksen seurauksena; silmätulehduksia ja stereotyyppistä käyttäytymistä havaittavissa. Kuvattu heinäkuussa ennen syksyn nahkontaa.',
      descEn: 'Wire-floored cages of blue foxes in open shed rows. Many animals overweight as a result of breeding; eye inflammation and stereotypic behaviour observed. Photographed in July, before the autumn pelting season.',
    },
    {
      id: 'a-uusikaarlepyy', projectId: 'p-turkis', seed: 23,
      fi: 'Minkkitarha, Uusikaarlepyy', en: 'Mink farm, Uusikaarlepyy',
      location: 'Uusikaarlepyy, Pohjanmaa', region: 'Pohjanmaa',
      date: '2023-08-09', photographer: 'Eero Niemelä',
      species: ['Minkki'], speciesEn: ['Mink'], use: 'Turkis', useEn: 'Fur',
      license: LICENSES.bync, mediaCount: 8, featured: false, coordsOptIn: false,
      descFi: 'Minkkien verkkohäkkejä. Vesieläimille tyypillistä uintimahdollisuutta ei tarjolla. Häkeissä havaittiin puremavammoja ja levotonta edestakaista liikettä.',
      descEn: 'Wire cages of mink. No swimming opportunity is provided to these semi-aquatic animals. Bite wounds and restless pacing were observed in the cages.',
    },
    {
      id: 'a-koylio', projectId: 'p-broiler', seed: 31,
      fi: 'Broilerihalli, Köyliö', en: 'Broiler hall, Köyliö',
      location: 'Köyliö, Satakunta', region: 'Satakunta',
      date: '2024-03-18', photographer: 'Laura Mäkinen',
      species: ['Broileri'], speciesEn: ['Broiler'], use: 'Lihantuotanto', useEn: 'Meat',
      license: LICENSES.byncnd, mediaCount: 11, featured: true, coordsOptIn: false,
      descFi: 'Yli 30 000 linnun kasvatushalli kasvatuksen loppupäässä, jolloin eläintiheys on suurimmillaan. Nopea kasvu rasittaa jalkoja ja sydäntä; osa linnuista ei kyennyt nousemaan. Kuivikepohja oli paikoin märkä ja ammoniakkipitoinen.',
      descEn: 'A grow-out hall of over 30,000 birds near the end of the cycle, when stocking density peaks. Rapid growth strains legs and heart; some birds could not stand. The litter floor was wet and ammonia-laden in places.',
    },
    {
      id: 'a-kiuruvesi', projectId: 'p-lypsy', seed: 41,
      fi: 'Parsinavetta, Kiuruvesi', en: 'Tie-stall barn, Kiuruvesi',
      location: 'Kiuruvesi, Pohjois-Savo', region: 'Pohjois-Savo',
      date: '2023-11-04', photographer: 'Tuomas Heikkilä',
      species: ['Nauta'], speciesEn: ['Cattle'], use: 'Maidontuotanto', useEn: 'Dairy',
      license: LICENSES.byncnd, mediaCount: 10, featured: true, coordsOptIn: false,
      descFi: 'Parsinavetta, jossa lypsylehmät on kytketty parsiin. Liikkuminen on rajattu seisomiseen ja makuulle käymiseen omalla paikalla. Vasikat oli eroteltu emoistaan erillisiin yksilökarsinoihin.',
      descEn: 'A tie-stall barn where dairy cows are tethered in stalls. Movement is limited to standing and lying within one\u2019s own place. Calves had been separated from their mothers into individual pens.',
    },
    {
      id: 'a-saarijarvi', projectId: 'p-kala', seed: 53,
      fi: 'Kirjolohilaitos, Saarijärvi', en: 'Rainbow trout facility, Saarijärvi',
      location: 'Saarijärvi, Keski-Suomi', region: 'Keski-Suomi',
      date: '2024-08-27', photographer: 'Anni Vuori',
      species: ['Lohi'], speciesEn: ['Salmon'], use: 'Vesiviljely', useEn: 'Aquaculture',
      license: LICENSES.edit, mediaCount: 9, featured: false, coordsOptIn: false,
      descFi: 'Maa-altaita ja verkkoaltaita kirjolohen kasvatukseen. Korkea kasvatustiheys ja eväkulumat näkyvissä. Sarja sisältää myös perkaamon ja kuljetuksen.',
      descEn: 'Earthen ponds and net pens for rainbow trout. High stocking density and fin erosion visible. The set also includes the gutting room and transport.',
    },
    {
      id: 'a-orimattila', projectId: 'p-muna', seed: 61,
      fi: 'Virikehäkkikanala, Orimattila', en: 'Enriched-cage layer house, Orimattila',
      location: 'Orimattila, Päijät-Häme', region: 'Päijät-Häme',
      date: '2025-02-12', photographer: 'Laura Mäkinen',
      species: ['Kana'], speciesEn: ['Hen'], use: 'Munantuotanto', useEn: 'Eggs',
      license: LICENSES.byncnd, mediaCount: 10, featured: true, coordsOptIn: false,
      descFi: 'Virikehäkkejä munivien kanojen tuotannossa. Häkit on varustettu orsilla ja pesäalueella, mutta tila lintua kohti on noin A4-arkin kokoinen. Höyhenpeitteen kuluminen oli yleistä.',
      descEn: 'Enriched cages in laying-hen production. Cages have perches and a nest area, but space per bird is about the size of an A4 sheet. Feather loss was common.',
    },
  ];

  // Per-album media specs (mix of images & a few videos, some with captions).
  const mediaSpecs = {
    'a-jokioinen': [
      { span: 'lg', fi: 'Yleiskuva kasvatushallista loppukasvatusvaiheessa.', en: 'Overview of the grow-out hall in the late fattening stage.' },
      { v: '0:48', fi: 'Karsinaolosuhteet, panorointi rivistön läpi.', en: 'Pen conditions, pan along the row.' },
      {},
      { fi: 'Hännänpurennan jälkiä usealla eläimellä.', en: 'Signs of tail-biting on several animals.' },
      { span: 'tall' },
      {},
      { fi: 'Ritiläpohjainen karsina ilman kuiviketta.', en: 'Slatted pen without bedding.' },
      {},
      { v: '1:12' },
      { span: 'wide', fi: 'Ruokintakäytävä ja automaattinen rehunjako.', en: 'Feed passage and automatic feeding.' },
      {},
      {},
      { fi: 'Yksittäinen eläin, ihovaurioita kyljessä.', en: 'A single animal with skin lesions on its flank.' },
      {},
    ],
    'a-loimaa': [
      { span: 'lg', fi: 'Tiineytyshäkkien rivistö porsitusosastolla.', en: 'Row of gestation crates in the farrowing section.' },
      {}, { v: '0:36' }, {}, { fi: 'Emakko porsitushäkissä.', en: 'Sow in a farrowing crate.' }, {}, {}, { span: 'tall' }, {},
    ],
    'a-kaustinen': [
      { span: 'lg' }, { fi: 'Sinikettu verkkopohjaisessa häkissä.', en: 'Blue fox in a wire-floored cage.' }, {}, { v: '0:52' },
      {}, { span: 'wide', fi: 'Varjotalon häkkirivistö.', en: 'Cage row in a shed.' }, {}, {}, { fi: 'Silmätulehdus lähikuvassa.', en: 'Eye inflammation, close-up.' }, {}, { span: 'tall' }, {},
    ],
    'a-uusikaarlepyy': [ { span: 'lg' }, {}, { v: '0:41' }, {}, {}, { span: 'tall' }, {}, {} ],
    'a-koylio': [
      { span: 'lg', fi: 'Kasvatushalli täydessä eläintiheydessä.', en: 'Grow-out hall at full stocking density.' },
      { v: '1:05' }, {}, {}, { fi: 'Lintu, joka ei kykene nousemaan.', en: 'A bird unable to stand.' }, { span: 'wide' }, {}, {}, { span: 'tall' }, {}, {},
    ],
    'a-kiuruvesi': [
      { span: 'lg' }, {}, { fi: 'Lypsylehmä kytkettynä parteen.', en: 'Dairy cow tethered in a stall.' }, { v: '0:58' }, {}, { span: 'tall' }, {}, { fi: 'Vasikka yksilökarsinassa.', en: 'Calf in an individual pen.' }, {}, {},
    ],
    'a-saarijarvi': [ { span: 'lg' }, {}, { v: '0:44' }, {}, { span: 'wide' }, {}, {}, {}, { span: 'tall' } ],
    'a-orimattila': [
      { span: 'lg' }, {}, {}, { fi: 'Virikehäkki orsineen.', en: 'Enriched cage with perches.' }, { v: '0:39' }, { span: 'tall' }, {}, {}, { fi: 'Höyhenpeitteen kulumaa.', en: 'Feather loss.' }, {},
    ],
  };

  const albums = albumDefs.map((a) => ({ ...a }));
  const media = [];
  albums.forEach((a) => {
    const specs = mediaSpecs[a.id] || Array.from({ length: a.mediaCount }, () => ({}));
    const m = makeMedia(a, specs);
    a.mediaIds = m.map((x) => x.id);
    a.cover = m[0];
    media.push(...m);
  });

  // Attach album lists to projects.
  projects.forEach((p) => { p.albumIds = albums.filter((a) => a.projectId === p.id).map((a) => a.id); });

  const byId = (arr) => Object.fromEntries(arr.map((x) => [x.id, x]));

  window.DATA = {
    projects, albums, media,
    projectsById: byId(projects),
    albumsById: byId(albums),
    mediaById: byId(media),
    photographers: PHOTOGRAPHERS,
    licenses: LICENSES,
    ORG: 'Eläintehtaat',
    // Curated featured selection for the homepage gallery (album covers + extras).
    featuredAlbums: albums.filter((a) => a.featured),
    // Newest first, for "Latest".
    latestAlbums: [...albums].sort((a, b) => b.date.localeCompare(a.date)),
    fmtDate,
    // Coarse taxonomy facets for browse.
    facets: {
      species: ['Sika', 'Nauta', 'Kana', 'Broileri', 'Kettu', 'Minkki', 'Lohi'],
      speciesEn: { Sika: 'Pig', Nauta: 'Cattle', Kana: 'Hen', Broileri: 'Broiler', Kettu: 'Fox', Minkki: 'Mink', Lohi: 'Salmon' },
      use: ['Lihantuotanto', 'Maidontuotanto', 'Munantuotanto', 'Turkis', 'Vesiviljely'],
      useEn: { Lihantuotanto: 'Meat', Maidontuotanto: 'Dairy', Munantuotanto: 'Eggs', Turkis: 'Fur', Vesiviljely: 'Aquaculture' },
      regions: ['Kanta-Häme', 'Varsinais-Suomi', 'Keski-Pohjanmaa', 'Pohjanmaa', 'Satakunta', 'Pohjois-Savo', 'Keski-Suomi', 'Päijät-Häme'],
      years: [2022, 2023, 2024, 2025],
    },
  };
})();
