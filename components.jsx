// components.jsx — shared UI for the archive. Exports to window for the
// other babel scripts (each script has its own transpile scope).

const Lang = React.createContext('fi');
const useLang = () => React.useContext(Lang);
const pick = (fi, en, lang) => (lang === 'en' ? (en ?? fi) : (fi ?? en));

// Locale-aware short date.
function fmtDate(iso, lang) {
  if (!iso) return '';
  const [y, m, d] = iso.split('-').map(Number);
  const moFi = ['', 'tammi', 'helmi', 'maalis', 'huhti', 'touko', 'kesä', 'heinä', 'elo', 'syys', 'loka', 'marras', 'joulu'];
  const moEn = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  return lang === 'en' ? `${moEn[m]} ${d}, ${y}` : `${d}. ${moFi[m]}kuuta ${y}`;
}

// ── Icons (simple line/shape glyphs) ─────────────────────────────────────
function Icon({ name, size = 16, stroke = 1.6 }) {
  const p = {
    play: <path d="M5 3.5v9l8-4.5z" fill="currentColor" stroke="none" />,
    arrow: <path d="M3 8h10M9 4l4 4-4 4" fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round" />,
    arrowL: <path d="M13 8H3M7 4 3 8l4 4" fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round" />,
    chevR: <path d="M6 3l5 5-5 5" fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round" />,
    chevL: <path d="M10 3 5 8l5 5" fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round" />,
    close: <path d="M4 4l8 8M12 4l-8 8" fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinecap="round" />,
    search: <g fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinecap="round"><circle cx="7" cy="7" r="4.2" /><path d="m11 11 3 3" /></g>,
    pin: <g fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinejoin="round"><path d="M8 14s5-4.2 5-8A5 5 0 0 0 3 6c0 3.8 5 8 5 8Z" /><circle cx="8" cy="6" r="1.7" /></g>,
    download: <path d="M8 2v8m0 0 3.2-3.2M8 10 4.8 6.8M3 13h10" fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinecap="round" strokeLinejoin="round" />,
    copy: <g fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinejoin="round"><rect x="5.5" y="5.5" width="8" height="8" rx="1.5" /><path d="M3.5 10.5V3.5h7" /></g>,
    cal: <g fill="none" stroke="currentColor" strokeWidth={stroke}><rect x="2.5" y="3.5" width="11" height="10" rx="1.5" /><path d="M2.5 6.5h11M5.5 2v3M10.5 2v3" strokeLinecap="round" /></g>,
    cam: <g fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinejoin="round"><path d="M2.5 5.5h2l1-1.5h5l1 1.5h2v8h-11z" /><circle cx="8" cy="9" r="2.3" /></g>,
    grid: <g fill="currentColor" stroke="none"><rect x="2.5" y="2.5" width="4.5" height="4.5" rx="1" /><rect x="9" y="2.5" width="4.5" height="4.5" rx="1" /><rect x="2.5" y="9" width="4.5" height="4.5" rx="1" /><rect x="9" y="9" width="4.5" height="4.5" rx="1" /></g>,
    filter: <path d="M2.5 4h11M5 8h6M7 12h2" fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinecap="round" />,
    video: <g fill="none" stroke="currentColor" strokeWidth={stroke} strokeLinejoin="round"><rect x="2" y="4.5" width="8.5" height="7" rx="1.5" /><path d="m10.5 7.5 3.5-2v5l-3.5-2z" /></g>,
    check: <path d="M3 8.5 6.5 12 13 4.5" fill="none" stroke="currentColor" strokeWidth={stroke + 0.3} strokeLinecap="round" strokeLinejoin="round" />,
  }[name];
  return <svg width={size} height={size} viewBox="0 0 16 16" aria-hidden="true" style={{ display: 'block' }}>{p}</svg>;
}

// ── Tile (image or video placeholder) ────────────────────────────────────
function tileBg(tone) {
  return `linear-gradient(${tone.ang}deg, ${tone.c1}, ${tone.c2})`;
}

function MediaTile({ media, onOpen, className = '', showCaption = true, showId = true }) {
  const lang = useLang();
  const cap = pick(media.captionFi, media.captionEn, lang);
  const isVideo = media.type === 'video';
  const num = media.id.split('-m')[1];
  return (
    <button
      className={`tile ${isVideo ? 'is-video' : ''} ${className}`}
      onClick={() => onOpen && onOpen(media)}
      aria-label={cap || (lang === 'en' ? 'Open media' : 'Avaa media')}
    >
      <span className="tile-fill" style={{ background: tileBg(media.tone) }} />
      <span className="tile-grain" />
      <span className="tile-vignette" />
      {showId && <span className="tile-id">{(media.albumId.replace('a-', '').slice(0, 3)).toUpperCase()}·{String(num).padStart(2, '0')}</span>}
      {isVideo && (
        <>
          <span className="tile-play"><i><Icon name="play" size={16} /></i></span>
          <span className="tile-badge"><Icon name="video" size={11} stroke={1.5} />{media.duration}</span>
        </>
      )}
      {showCaption && (
        <>
          <span className="tile-shade" />
          {cap && <span className="tile-meta"><span className="tile-cap">{cap}</span></span>}
        </>
      )}
    </button>
  );
}

// ── Masthead ──────────────────────────────────────────────────────────────
function Masthead({ route, lang, setLang, nav }) {
  const link = (name, label) => (
    <a
      className={route.name === name ? 'active' : ''}
      onClick={(e) => { e.preventDefault(); nav({ name }); }}
      href="#"
    >{label}</a>
  );
  return (
    <header className="masthead">
      <div className="shell-wide masthead-row">
        <a className="wordmark" href="#" onClick={(e) => { e.preventDefault(); nav({ name: 'home' }); }}>
          <b>Eläintehtaat</b>
          <span className="tag">kuva-arkisto</span>
        </a>
        <nav className="mast-nav">
          {link('browse', pick('Selaa', 'Browse', lang))}
          {link('projects', pick('Projektit', 'Projects', lang))}
          <a href="#" onClick={(e) => e.preventDefault()}>{pick('Käyttöehdot', 'Licensing', lang)}</a>
          <a href="#" onClick={(e) => e.preventDefault()}>{pick('Tietoa', 'About', lang)}</a>
        </nav>
        <div className="mast-spacer" />
        <div className="mast-tools">
          <button className="search-stub" onClick={() => nav({ name: 'browse' })}>
            <Icon name="search" size={15} />
            <span>{pick('Hae arkistosta', 'Search archive', lang)}</span>
            <kbd>/</kbd>
          </button>
          <div className="lang-toggle">
            <button className={lang === 'fi' ? 'on' : ''} onClick={() => setLang('fi')}>FI</button>
            <button className={lang === 'en' ? 'on' : ''} onClick={() => setLang('en')}>EN</button>
          </div>
        </div>
      </div>
    </header>
  );
}

// ── License chip + attribution ────────────────────────────────────────────
function LicenseChip({ license, lang }) {
  return (
    <span className="license" title={pick(license.fi, license.en, lang)}>
      <span className="dot" />{license.code}
    </span>
  );
}

function speciesLabel(album, lang) {
  return (lang === 'en' ? album.speciesEn : album.species).join(', ');
}
function useLabel(album, lang) {
  return lang === 'en' ? album.useEn : album.use;
}

// ── Album card (used in latest / project / browse contexts) ───────────────
function AlbumCard({ album, nav, big = false }) {
  const lang = useLang();
  const project = DATA.projectsById[album.projectId];
  return (
    <a className="album-card" href="#" onClick={(e) => { e.preventDefault(); nav({ name: 'album', albumId: album.id }); }}>
      <div className="thumb">
        <MediaTile media={album.cover} showCaption={false} showId={false} onOpen={() => nav({ name: 'album', albumId: album.id })} />
        <span className="count">{album.mediaCount} {pick('mediaa', 'media', lang)}</span>
      </div>
      <div className="stack" style={{ gap: 7 }}>
        <div className="eyebrow">{pick(project.fi, project.en, lang)} · {project.year}</div>
        <h3>{pick(album.fi, album.en, lang)}</h3>
        <div className="meta-line muted">
          <span className="row" style={{ gap: 5 }}><Icon name="pin" size={13} stroke={1.5} />{album.location}</span>
          <span>{speciesLabel(album, lang)}</span>
        </div>
      </div>
    </a>
  );
}

// ── Footer ──────────────────────────────────────────────────────────────
function Footer({ lang, nav }) {
  return (
    <footer className="foot">
      <div className="shell-wide">
        <div className="foot-row">
          <div style={{ maxWidth: 340 }}>
            <div className="wordmark" style={{ marginBottom: 12 }}>
              <b>Eläintehtaat</b><span className="tag">kuva-arkisto</span>
            </div>
            <p className="muted" style={{ fontSize: 13.5, lineHeight: 1.6 }}>
              {pick(
                'Avoin kuva- ja videoarkisto eläintuotannon olosuhteista. Aineisto on toimittajien, tutkijoiden ja kansalaisten käytettävissä lisenssiehtojen mukaisesti.',
                'An open image and video archive documenting conditions in animal production. Material is available to journalists, researchers and the public under its license terms.',
                lang
              )}
            </p>
          </div>
          <div>
            <h4>{pick('Arkisto', 'Archive', lang)}</h4>
            <a href="#" onClick={(e) => { e.preventDefault(); nav({ name: 'browse' }); }}>{pick('Selaa kaikkia', 'Browse all', lang)}</a>
            <a href="#" onClick={(e) => { e.preventDefault(); nav({ name: 'projects' }); }}>{pick('Projektit', 'Projects', lang)}</a>
            <a href="#" onClick={(e) => e.preventDefault()}>{pick('Tuoreimmat', 'Latest', lang)}</a>
          </div>
          <div>
            <h4>{pick('Käyttö', 'Use', lang)}</h4>
            <a href="#" onClick={(e) => e.preventDefault()}>{pick('Lisenssit', 'Licensing', lang)}</a>
            <a href="#" onClick={(e) => e.preventDefault()}>{pick('Attribuutio-ohjeet', 'Attribution', lang)}</a>
            <a href="#" onClick={(e) => e.preventDefault()}>{pick('Median lataaminen', 'Downloads', lang)}</a>
          </div>
          <div>
            <h4>{pick('Yhteys', 'Contact', lang)}</h4>
            <a href="#" onClick={(e) => e.preventDefault()}>{pick('Toimitus', 'Editorial', lang)}</a>
            <a href="#" onClick={(e) => e.preventDefault()}>arkisto@elaintehtaat.fi</a>
          </div>
        </div>
      </div>
    </footer>
  );
}

Object.assign(window, {
  Lang, useLang, pick, fmtDate, Icon, MediaTile, tileBg,
  Masthead, LicenseChip, AlbumCard, Footer, speciesLabel, useLabel,
});
