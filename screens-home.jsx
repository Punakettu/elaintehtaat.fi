// screens-home.jsx — homepage serving all three audiences:
//  followers (Latest + projects), public (Featured gallery + scale),
//  activists/re-publishers (Filter path).

function FeaturedMosaic({ nav }) {
  const lang = useLang();
  // Assemble a curated set: each featured album's cover + a couple of extra
  // frames, so the mosaic conveys volume. Clicking opens the album (album-centric).
  const tiles = [];
  DATA.featuredAlbums.forEach((a, i) => {
    tiles.push({ media: a.cover, album: a });
    const extra = DATA.media.find((m) => m.albumId === a.id && m.id !== a.cover.id && (i % 2 === 0 ? m.type === 'video' : true));
    if (extra) tiles.push({ media: extra, album: a });
  });
  const set = tiles.slice(0, 11);
  const spanFor = (i) => (i === 0 ? 'm-hero' : i === 3 ? 'm-tall' : i === 6 ? 'm-wide' : i === 9 ? 'm-tall' : '');
  return (
    <div className="mosaic">
      {set.map((t, i) => (
        <div key={t.media.id} className={spanFor(i)}>
          <MediaTile media={t.media} onOpen={() => nav({ name: 'album', albumId: t.album.id })} />
        </div>
      ))}
    </div>
  );
}

function PathCard({ n, title, sub, desc, cta, onClick }) {
  return (
    <button className="path-card" onClick={onClick}>
      <div className="path-top">
        <span className="path-n mono">{n}</span>
        <span className="path-arrow"><Icon name="arrow" size={18} /></span>
      </div>
      <h3 className="display">{title}</h3>
      <div className="path-sub lang-2">{sub}</div>
      <p className="muted">{desc}</p>
      <span className="path-cta">{cta}</span>
    </button>
  );
}

function Home({ nav }) {
  const lang = useLang();
  const scale = {
    albums: DATA.albums.length,
    media: DATA.media.length,
    projects: DATA.projects.length,
    regions: new Set(DATA.albums.map((a) => a.region)).size,
  };
  const latest = DATA.latestAlbums.slice(0, 4);

  return (
    <main>
      {/* Hero band */}
      <section className="shell-wide" style={{ paddingTop: 'clamp(40px, 7vh, 88px)' }}>
        <div className="hero-grid">
          <div className="hero-copy">
            <div className="eyebrow">{pick('Avoin kuva- ja videoarkisto', 'Open image & video archive', lang)}</div>
            <h1 className="display hero-title">
              {pick('Tuotantoeläinten olosuhteet, dokumentoituna.', 'The conditions of farmed animals, on record.', lang)}
            </h1>
            <p className="hero-lede muted">
              {pick(
                'Arkisto kokoaa kuvat ja videot eläintuotannosta yhteen. Jokainen albumi kertoo yhden paikan tarinan — toimittajien, tutkijoiden ja kansalaisten käytettävissä.',
                'A single archive of images and video from animal production. Each album tells the story of one place — for journalists, researchers and the public.',
                lang
              )}
            </p>
            <div className="scale-line">
              <div><b className="mono">{scale.albums}</b><span>{pick('albumia', 'albums', lang)}</span></div>
              <div><b className="mono">{scale.media}</b><span>{pick('mediaa', 'media', lang)}</span></div>
              <div><b className="mono">{scale.projects}</b><span>{pick('projektia', 'projects', lang)}</span></div>
              <div><b className="mono">{scale.regions}</b><span>{pick('maakuntaa', 'regions', lang)}</span></div>
            </div>
          </div>
        </div>
      </section>

      {/* Featured gallery */}
      <section className="shell-wide" style={{ marginTop: 'clamp(28px, 5vh, 52px)' }}>
        <div className="sec-head" style={{ marginBottom: 16 }}>
          <div className="row" style={{ gap: 14, alignItems: 'baseline' }}>
            <h2 style={{ fontSize: 22 }}>{pick('Esiin nostettua', 'Featured', lang)}</h2>
            <span className="lang-2" style={{ fontSize: 14 }}>{pick('Featured', 'Esiin nostettua', lang)}</span>
          </div>
          <a className="alink" href="#" onClick={(e) => { e.preventDefault(); nav({ name: 'browse' }); }}>{pick('Selaa koko arkisto', 'Browse the whole library', lang)} →</a>
        </div>
        <FeaturedMosaic nav={nav} />
      </section>

      {/* Three paths */}
      <section className="shell-wide" style={{ marginTop: 'clamp(40px, 6vh, 72px)' }}>
        <div className="paths">
          <PathCard
            n="01"
            title={pick('Tuoreimmat', 'Latest', lang)}
            sub={pick('Latest publications', 'Tuoreimmat julkaisut', lang)}
            desc={pick('Seuraa uusimpia projekteja ja julkaisuja aikajärjestyksessä.', 'Follow the newest projects and publications in order of release.', lang)}
            cta={pick('Katso tuoreimmat', 'See latest', lang)}
            onClick={() => {
              const el = document.getElementById('latest-sec');
              if (el) {
                const top = el.getBoundingClientRect().top + window.scrollY - 80;
                window.scrollTo({ top, behavior: 'smooth' });
              } else { nav({ name: 'browse' }); }
            }}
          />
          <PathCard
            n="02"
            title={pick('Selaa arkisto', 'Browse library', lang)}
            sub={pick('The whole library', 'Koko arkisto', lang)}
            desc={pick('Käy läpi koko aineisto galleriaruudukossa — projektien ja albumien laajuudessa.', 'Move through the entire collection in a gallery grid — across projects and albums.', lang)}
            cta={pick('Avaa galleria', 'Open gallery', lang)}
            onClick={() => nav({ name: 'browse' })}
          />
          <PathCard
            n="03"
            title={pick('Suodata', 'Filter', lang)}
            sub={pick('By taxonomy', 'Taksonomian mukaan', lang)}
            desc={pick('Rajaa laji, käyttötarkoitus, sijainti, kuvaaja ja vuosi — yhdisteltävät suodattimet.', 'Narrow by species, use, location, photographer and year — combinable facets.', lang)}
            cta={pick('Suodata aineistoa', 'Filter material', lang)}
            onClick={() => nav({ name: 'browse', openFilters: true })}
          />
        </div>
      </section>

      {/* Latest */}
      <section id="latest-sec" className="shell-wide" style={{ marginTop: 'clamp(44px, 7vh, 84px)' }}>
        <div className="sec-head" style={{ marginBottom: 22 }}>
          <div className="row" style={{ gap: 14, alignItems: 'baseline' }}>
            <h2 style={{ fontSize: 22 }}>{pick('Tuoreimmat albumit', 'Latest albums', lang)}</h2>
            <span className="lang-2" style={{ fontSize: 14 }}>{pick('Latest', 'Tuoreimmat', lang)}</span>
          </div>
          <a className="alink" href="#" onClick={(e) => { e.preventDefault(); nav({ name: 'browse' }); }}>{pick('Kaikki albumit', 'All albums', lang)} →</a>
        </div>
        <div className="grid cards-4">
          {latest.map((a) => <AlbumCard key={a.id} album={a} nav={nav} />)}
        </div>
      </section>

      {/* Projects (for followers) */}
      <section className="shell-wide" style={{ marginTop: 'clamp(44px, 7vh, 84px)' }}>
        <div className="sec-head" style={{ marginBottom: 18 }}>
          <div className="row" style={{ gap: 14, alignItems: 'baseline' }}>
            <h2 style={{ fontSize: 22 }}>{pick('Projektit', 'Projects', lang)}</h2>
            <span className="lang-2" style={{ fontSize: 14 }}>{DATA.projects.length}</span>
          </div>
        </div>
        <div className="proj-list">
          {DATA.projects.map((p) => {
            const albums = p.albumIds.map((id) => DATA.albumsById[id]);
            return (
              <button key={p.id} className="proj-row" onClick={() => nav({ name: 'browse', project: p.id })}>
                <div className="proj-year mono">{p.year}</div>
                <div className="proj-main">
                  <div className="proj-title display">{pick(p.fi, p.en, lang)}</div>
                  <p className="muted proj-blurb">{pick(p.blurbFi, p.blurbEn, lang)}</p>
                </div>
                <div className="proj-thumbs">
                  {albums.slice(0, 4).map((a) => (
                    <span key={a.id} className="proj-thumb" style={{ background: tileBg(a.cover.tone) }} />
                  ))}
                </div>
                <div className="proj-count mono">{p.albumIds.length} {pick('albumia', 'albums', lang)} <Icon name="chevR" size={13} /></div>
              </button>
            );
          })}
        </div>
      </section>
    </main>
  );
}

Object.assign(window, { Home });
