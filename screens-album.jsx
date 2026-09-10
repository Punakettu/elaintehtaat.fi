// screens-album.jsx — album detail. The album is the primary descriptive unit:
// description prominent, then a responsive grid of its media. Three layout
// variants (sidebar / stacked / immersive) are exposed as a Tweak.

function AlbumMeta({ album, lang, variant }) {
  const project = DATA.projectsById[album.projectId];
  const Row = ({ icon, dt, children }) => (
    <>
      <dt>{dt}</dt>
      <dd>{children}</dd>
    </>
  );
  return (
    <dl className="dl">
      <Row dt={pick('Sijainti', 'Location', lang)}>
        <span className="row" style={{ gap: 6 }}><Icon name="pin" size={13} stroke={1.5} />{album.location}</span>
        {!album.coordsOptIn && <div className="faint" style={{ fontSize: 11.5, marginTop: 3 }}>{pick('Karkea sijainti', 'Coarse location', lang)}</div>}
      </Row>
      <Row dt={pick('Päivämäärä', 'Date', lang)}>{fmtDate(album.date, lang)}</Row>
      <Row dt={pick('Laji', 'Species', lang)}>{speciesLabel(album, lang)}</Row>
      <Row dt={pick('Käyttö', 'Use', lang)}>{useLabel(album, lang)}</Row>
      <Row dt={pick('Kuvaaja', 'Photographer', lang)}>{album.photographer}</Row>
      <Row dt={pick('Projekti', 'Project', lang)}>{pick(project.fi, project.en, lang)} · {project.year}</Row>
      <Row dt={pick('Lisenssi', 'License', lang)}><LicenseChip license={album.license} lang={lang} /></Row>
    </dl>
  );
}

function AttributionBlock({ album, lang }) {
  const [copied, setCopied] = React.useState(false);
  const str = `${pick('Kuva', 'Photo', lang)}: ${album.photographer} / ${DATA.ORG}`;
  const copy = () => {
    try { navigator.clipboard?.writeText(str); } catch (e) {}
    setCopied(true); setTimeout(() => setCopied(false), 1600);
  };
  return (
    <div className="attrib">
      <div className="eyebrow">{pick('Vaadittu maininta', 'Required attribution', lang)}</div>
      <code>{str}</code>
      <button className="copy" onClick={copy}>
        <Icon name={copied ? 'check' : 'copy'} size={13} />{copied ? pick('Kopioitu', 'Copied', lang) : pick('Kopioi maininta', 'Copy attribution', lang)}
      </button>
    </div>
  );
}

function Crumbs({ album, lang, nav }) {
  const project = DATA.projectsById[album.projectId];
  return (
    <div className="crumbs">
      <a href="#" onClick={(e) => { e.preventDefault(); nav({ name: 'home' }); }}>{pick('Arkisto', 'Archive', lang)}</a>
      <span className="sep">/</span>
      <a href="#" onClick={(e) => { e.preventDefault(); nav({ name: 'browse', project: project.id }); }}>{pick(project.fi, project.en, lang)}</a>
      <span className="sep">/</span>
      <span className="cur">{pick(album.fi, album.en, lang)}</span>
    </div>
  );
}

function AlbumGrid({ album, onOpenMedia }) {
  const items = album.mediaIds.map((id) => DATA.mediaById[id]);
  const spanClass = (m) => (m.span === 'lg' ? 'span-lg' : m.span === 'wide' ? 'span-wide' : m.span === 'tall' ? 'span-tall' : '');
  return (
    <div className="grid grid-album">
      {items.map((m) => (
        <div key={m.id} className={spanClass(m)}>
          <MediaTile media={m} onOpen={onOpenMedia} />
        </div>
      ))}
    </div>
  );
}

function ProjectContext({ album, lang, nav }) {
  const project = DATA.projectsById[album.projectId];
  const siblings = project.albumIds.map((id) => DATA.albumsById[id]).filter((a) => a.id !== album.id);
  if (!siblings.length) return null;
  return (
    <section className="shell-wide" style={{ marginTop: 'clamp(48px, 7vh, 80px)' }}>
      <hr className="rule" style={{ marginBottom: 26 }} />
      <div className="sec-head" style={{ marginBottom: 20 }}>
        <div className="stack" style={{ gap: 4 }}>
          <div className="eyebrow">{pick('Saman projektin albumit', 'More from this project', lang)}</div>
          <h2 style={{ fontSize: 20 }}>{pick(project.fi, project.en, lang)}</h2>
        </div>
        <a className="alink" href="#" onClick={(e) => { e.preventDefault(); nav({ name: 'browse', project: project.id }); }}>{pick('Koko projekti', 'Whole project', lang)} →</a>
      </div>
      <div className="grid cards-4">
        {siblings.slice(0, 4).map((a) => <AlbumCard key={a.id} album={a} nav={nav} />)}
      </div>
    </section>
  );
}

function AlbumDetail({ albumId, layout, nav, onOpenMedia }) {
  const lang = useLang();
  const album = DATA.albumsById[albumId];
  React.useEffect(() => { window.scrollTo(0, 0); }, [albumId]);
  if (!album) return null;
  const project = DATA.projectsById[album.projectId];
  const desc = pick(album.descFi, album.descEn, lang);
  const eyebrow = `${pick(project.fi, project.en, lang)} · ${project.year}`;

  const Header = () => {
    if (layout === 'stacked') {
      return (
        <header className="shell-wide al-stacked">
          <Crumbs album={album} lang={lang} nav={nav} />
          <div className="eyebrow" style={{ marginTop: 22 }}>{eyebrow}</div>
          <h1 className="display al-title">{pick(album.fi, album.en, lang)}</h1>
          <p className="al-desc">{desc}</p>
          <div className="al-stacked-meta">
            <AlbumMeta album={album} lang={lang} variant="stacked" />
          </div>
          <div className="al-actions">
            <LicenseChip license={album.license} lang={lang} />
            <span className="faint" style={{ fontSize: 12.5 }}>{pick('Kuva', 'Photo', lang)}: {album.photographer} / {DATA.ORG}</span>
          </div>
        </header>
      );
    }
    if (layout === 'immersive') {
      return (
        <header>
          <div className="al-hero">
            <MediaTile media={album.cover} showCaption={false} showId={false} onOpen={onOpenMedia} />
            <div className="al-hero-grad" />
            <div className="shell-wide al-hero-copy">
              <Crumbs album={album} lang={lang} nav={nav} />
              <div className="eyebrow" style={{ marginTop: 16 }}>{eyebrow}</div>
              <h1 className="display al-title" style={{ maxWidth: 900 }}>{pick(album.fi, album.en, lang)}</h1>
              <div className="row wrap" style={{ gap: 14, marginTop: 14 }}>
                <LicenseChip license={album.license} lang={lang} />
                <span className="tag-chip"><Icon name="pin" size={13} stroke={1.5} />{album.location}</span>
                <span className="tag-chip"><Icon name="cal" size={13} stroke={1.5} />{fmtDate(album.date, lang)}</span>
                <span className="tag-chip">{album.mediaCount} {pick('mediaa', 'media', lang)}</span>
              </div>
            </div>
          </div>
          <div className="shell-wide al-immersive-body">
            <p className="al-desc">{desc}</p>
            <div className="al-immersive-meta"><AlbumMeta album={album} lang={lang} variant="immersive" /></div>
          </div>
        </header>
      );
    }
    // default: sidebar
    return (
      <header className="shell-wide al-sidebar">
        <div className="al-side-main">
          <Crumbs album={album} lang={lang} nav={nav} />
          <div className="eyebrow" style={{ marginTop: 22 }}>{eyebrow}</div>
          <h1 className="display al-title">{pick(album.fi, album.en, lang)}</h1>
          <p className="al-desc">{desc}</p>
          <AttributionBlock album={album} lang={lang} />
        </div>
        <aside className="al-side-rail">
          <div className="al-meta-card">
            <AlbumMeta album={album} lang={lang} variant="sidebar" />
            <button className="btn btn-ghost btn-sm" style={{ width: '100%', marginTop: 18 }} onClick={() => onOpenMedia(album.cover)}>
              {pick('Avaa ensimmäinen media', 'Open first media', lang)}
            </button>
          </div>
        </aside>
      </header>
    );
  };

  return (
    <main className={`al-screen al-${layout}`}>
      <Header />
      <section className="shell-wide al-grid-sec">
        <div className="sec-head" style={{ marginBottom: 16 }}>
          <div className="row" style={{ gap: 12, alignItems: 'baseline' }}>
            <h2 style={{ fontSize: 18 }}>{pick('Albumin media', 'Album media', lang)}</h2>
            <span className="lang-2 mono" style={{ fontSize: 13 }}>{album.mediaCount} {pick('kpl', 'items', lang)}</span>
          </div>
          <div className="note" style={{ maxWidth: 280 }}>
            <Icon name="pin" size={13} stroke={1.5} />
            <span>{pick('Tarkka sijainti on arkaluonteista — näytetään vain karkealla tasolla.', 'Precise location is sensitive — shown only at a coarse level.', lang)}</span>
          </div>
        </div>
        <AlbumGrid album={album} onOpenMedia={onOpenMedia} />
      </section>
      <ProjectContext album={album} lang={lang} nav={nav} />
    </main>
  );
}

Object.assign(window, { AlbumDetail });
