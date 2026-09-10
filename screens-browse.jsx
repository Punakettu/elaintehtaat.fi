// screens-browse.jsx — Browse/filter view (combinable taxonomy facets,
// results spanning projects/albums) + the Media detail lightbox.

// ── Media detail lightbox ────────────────────────────────────────────────
function Lightbox({ mediaId, context, onClose, onOpenMedia, nav }) {
  const lang = useLang();
  const media = DATA.mediaById[mediaId];
  const album = media && DATA.albumsById[media.albumId];
  const ids = context && context.length ? context : (album ? album.mediaIds : [mediaId]);
  const idx = ids.indexOf(mediaId);
  const [gate, setGate] = React.useState(false);
  const [agreed, setAgreed] = React.useState(false);
  const [done, setDone] = React.useState(false);

  const go = React.useCallback((d) => {
    const ni = (idx + d + ids.length) % ids.length;
    onOpenMedia(ids[ni], ids);
    setGate(false); setAgreed(false); setDone(false);
  }, [idx, ids, onOpenMedia]);

  React.useEffect(() => {
    const onKey = (e) => {
      if (e.key === 'Escape') onClose();
      else if (e.key === 'ArrowRight') go(1);
      else if (e.key === 'ArrowLeft') go(-1);
    };
    window.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    return () => { window.removeEventListener('keydown', onKey); document.body.style.overflow = ''; };
  }, [go, onClose]);

  if (!media || !album) return null;
  const cap = pick(media.captionFi, media.captionEn, lang);
  const project = DATA.projectsById[album.projectId];
  const isVideo = media.type === 'video';
  const num = media.id.split('-m')[1];
  const attribution = `${pick('Kuva', 'Photo', lang)}: ${media.photographer} / ${DATA.ORG}`;

  return (
    <div className="lb-overlay" onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="lb-stage" onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
        <button className="lb-close" onClick={onClose} aria-label="Close"><Icon name="close" size={16} /></button>
        <span className="lb-counter">{String(idx + 1).padStart(2, '0')} / {String(ids.length).padStart(2, '0')}</span>
        {ids.length > 1 && <button className="lb-nav lb-prev" onClick={() => go(-1)} aria-label="Previous"><Icon name="chevL" size={18} /></button>}
        <div className="lb-frame">
          <span className="tile-fill" style={{ background: tileBg(media.tone) }} />
          <span className="tile-grain" />
          <span className="tile-vignette" />
          {isVideo && (
            <div className="player">
              <button className="player-play" aria-label="Play"><i><Icon name="play" size={26} /></i></button>
              <div className="player-bar">
                <Icon name="play" size={13} />
                <div className="track"><i /></div>
                <span className="t">0:16 / {media.duration}</span>
              </div>
            </div>
          )}
          <span className="lb-frametag mono">{(media.albumId.replace('a-', '').slice(0, 3)).toUpperCase()}·{String(num).padStart(2, '0')}</span>
        </div>
        {ids.length > 1 && <button className="lb-nav lb-next" onClick={() => go(1)} aria-label="Next"><Icon name="chevR" size={18} /></button>}
      </div>

      <aside className="lb-panel" onClick={(e) => e.stopPropagation()}>
        <div className="lb-panel-inner">
          <div className="stack" style={{ gap: 6 }}>
            <div className="eyebrow">{isVideo ? pick('Video', 'Video', lang) : pick('Valokuva', 'Photograph', lang)} · {fmtDate(media.captureDate, lang)}</div>
            {cap ? (
              <p className="lb-cap">{cap}</p>
            ) : (
              <p className="lb-cap faint">
                {pick('Ei erillistä kuvatekstiä. ', 'No separate caption. ', lang)}
                <a className="alink" href="#" onClick={(e) => { e.preventDefault(); onClose(); nav({ name: 'album', albumId: album.id }); }}>
                  {pick(`Osa albumia "${album.fi}"`, `Part of album “${album.en}”`, lang)}
                </a>
                {pick(' — katso albumin kuvaus.', ' — see album for details.', lang)}
              </p>
            )}
          </div>

          <button className="lb-album-link" onClick={() => { onClose(); nav({ name: 'album', albumId: album.id }); }}>
            <span className="thumb-mini" style={{ background: tileBg(album.cover.tone) }} />
            <span className="stack" style={{ gap: 2, textAlign: 'left' }}>
              <span className="eyebrow">{pick('Albumi', 'Album', lang)}</span>
              <span style={{ fontSize: 14, fontWeight: 600 }}>{pick(album.fi, album.en, lang)}</span>
              <span className="faint" style={{ fontSize: 12 }}>{pick(project.fi, project.en, lang)} · {project.year}</span>
            </span>
            <Icon name="chevR" size={14} />
          </button>

          <dl className="dl">
            <dt>{pick('Kuvaaja', 'Photographer', lang)}</dt><dd>{media.photographer}</dd>
            <dt>{pick('Sijainti', 'Location', lang)}</dt>
            <dd><span className="row" style={{ gap: 6 }}><Icon name="pin" size={13} stroke={1.5} />{album.location}</span>
              <div className="faint" style={{ fontSize: 11.5, marginTop: 2 }}>{pick('Karkea sijainti', 'Coarse location', lang)}</div></dd>
            <dt>{pick('Kuvattu', 'Captured', lang)}</dt><dd>{fmtDate(media.captureDate, lang)}</dd>
            <dt>{pick('Lisenssi', 'License', lang)}</dt><dd><LicenseChip license={media.license} lang={lang} /><div className="faint" style={{ fontSize: 11.5, marginTop: 5 }}>{pick(media.license.fi, media.license.en, lang)}</div></dd>
          </dl>

          <div className="attrib">
            <div className="eyebrow">{pick('Vaadittu maininta', 'Required attribution', lang)}</div>
            <code>{attribution}</code>
            <button className="copy" onClick={() => { try { navigator.clipboard?.writeText(attribution); } catch (e) {} }}>
              <Icon name="copy" size={13} />{pick('Kopioi maininta', 'Copy attribution', lang)}
            </button>
          </div>

          {/* license-tied download */}
          {!gate ? (
            <button className="btn btn-accent" onClick={() => setGate(true)}>
              <Icon name="download" size={15} />{pick('Lataa media', 'Download media', lang)}
            </button>
          ) : done ? (
            <div className="dl-done"><Icon name="check" size={15} /> {pick('Lataus alkoi — muista vaadittu maininta.', 'Download started — remember the required attribution.', lang)}</div>
          ) : (
            <div className="dl-gate">
              <label className="dl-agree">
                <input type="checkbox" checked={agreed} onChange={(e) => setAgreed(e.target.checked)} />
                <span>{pick(`Hyväksyn lisenssin (${media.license.code}) ja sitoudun käyttämään vaadittua mainintaa.`, `I accept the license (${media.license.code}) and will use the required attribution.`, lang)}</span>
              </label>
              <div className="row" style={{ gap: 8 }}>
                <button className="btn btn-accent btn-sm" disabled={!agreed} style={{ opacity: agreed ? 1 : 0.45 }} onClick={() => setDone(true)}>
                  {pick('Lataa alkuperäinen', 'Download original', lang)}
                </button>
                <button className="btn btn-ghost btn-sm" onClick={() => setGate(false)}>{pick('Peruuta', 'Cancel', lang)}</button>
              </div>
            </div>
          )}
        </div>
      </aside>
    </div>
  );
}

// ── Browse / filter ───────────────────────────────────────────────────────
const EMPTY_FILTERS = { species: [], use: [], regions: [], photographers: [], years: [] };

function FacetGroup({ title, options, active, onToggle, fmt }) {
  return (
    <div className="facet">
      <div className="facet-title eyebrow">{title}</div>
      <div className="facet-chips">
        {options.map((o) => (
          <button key={o} className={`tag-chip ${active.includes(o) ? 'on' : ''}`} onClick={() => onToggle(o)}>
            {active.includes(o) && <Icon name="check" size={11} />}
            {fmt ? fmt(o) : o}
          </button>
        ))}
      </div>
    </div>
  );
}

function Browse({ route, nav, onOpenMedia }) {
  const lang = useLang();
  const [filters, setFilters] = React.useState(() => ({ ...EMPTY_FILTERS }));
  const [project, setProject] = React.useState(route.project || null);
  const [showFilters, setShowFilters] = React.useState(true);
  React.useEffect(() => { window.scrollTo(0, 0); }, []);
  React.useEffect(() => { setProject(route.project || null); }, [route.project]);

  const toggle = (key, val) => setFilters((f) => ({ ...f, [key]: f[key].includes(val) ? f[key].filter((x) => x !== val) : [...f[key], val] }));
  const clearAll = () => { setFilters({ ...EMPTY_FILTERS }); setProject(null); };

  // Join media with album facets, then filter.
  const results = React.useMemo(() => {
    return DATA.media.filter((m) => {
      const a = DATA.albumsById[m.albumId];
      if (project && a.projectId !== project) return false;
      if (filters.species.length && !a.species.some((s) => filters.species.includes(s))) return false;
      if (filters.use.length && !filters.use.includes(a.use)) return false;
      if (filters.regions.length && !filters.regions.includes(a.region)) return false;
      if (filters.photographers.length && !filters.photographers.includes(m.photographer)) return false;
      if (filters.years.length && !filters.years.includes(DATA.projectsById[a.projectId].year)) return false;
      return true;
    });
  }, [filters, project]);
  const resultIds = results.map((m) => m.id);

  const activeCount = Object.values(filters).reduce((n, a) => n + a.length, 0) + (project ? 1 : 0);
  const projObj = project ? DATA.projectsById[project] : null;

  return (
    <main className="browse">
      <div className="shell-wide">
        <div className="browse-head">
          <div className="stack" style={{ gap: 6 }}>
            <div className="eyebrow">{pick('Selaa arkistoa', 'Browse the archive', lang)}</div>
            <div className="row wrap" style={{ gap: 14, alignItems: 'baseline' }}>
              <h1 className="display" style={{ fontSize: 28 }}>{pick('Koko aineisto', 'The whole library', lang)}</h1>
              <span className="muted mono" style={{ fontSize: 14 }}>{results.length} {pick('mediaa', 'media', lang)}</span>
            </div>
          </div>
          <button className="btn btn-ghost btn-sm browse-filter-toggle" onClick={() => setShowFilters((s) => !s)}>
            <Icon name="filter" size={15} />{pick('Suodattimet', 'Filters', lang)}{activeCount ? ` (${activeCount})` : ''}
          </button>
        </div>

        <div className={`browse-body ${showFilters ? '' : 'no-filters'}`}>
          {showFilters && (
            <aside className="filters">
              <div className="filters-top">
                <span className="eyebrow">{pick('Suodattimet', 'Filters', lang)}</span>
                {activeCount > 0 && <button className="clear-link" onClick={clearAll}>{pick('Tyhjennä', 'Clear', lang)} ({activeCount})</button>}
              </div>

              {projObj && (
                <div className="facet">
                  <div className="facet-title eyebrow">{pick('Projekti', 'Project', lang)}</div>
                  <button className="tag-chip on" onClick={() => setProject(null)}>
                    <Icon name="close" size={11} />{pick(projObj.fi, projObj.en, lang)}
                  </button>
                </div>
              )}

              <FacetGroup title={pick('Laji', 'Species', lang)} options={DATA.facets.species} active={filters.species}
                onToggle={(v) => toggle('species', v)} fmt={(o) => (lang === 'en' ? DATA.facets.speciesEn[o] : o)} />
              <FacetGroup title={pick('Käyttötarkoitus', 'Use category', lang)} options={DATA.facets.use} active={filters.use}
                onToggle={(v) => toggle('use', v)} fmt={(o) => (lang === 'en' ? DATA.facets.useEn[o] : o)} />
              <FacetGroup title={pick('Sijainti', 'Location', lang)} options={DATA.facets.regions} active={filters.regions}
                onToggle={(v) => toggle('regions', v)} />
              <FacetGroup title={pick('Kuvaaja', 'Photographer', lang)} options={DATA.photographers} active={filters.photographers}
                onToggle={(v) => toggle('photographers', v)} />
              <FacetGroup title={pick('Vuosi', 'Year', lang)} options={DATA.facets.years} active={filters.years}
                onToggle={(v) => toggle('years', v)} />
            </aside>
          )}

          <div className="browse-results">
            {activeCount > 0 && (
              <div className="active-row">
                {project && <button className="tag-chip on" onClick={() => setProject(null)}><Icon name="close" size={11} />{pick(projObj.fi, projObj.en, lang)}</button>}
                {filters.species.map((v) => <button key={'s' + v} className="tag-chip on" onClick={() => toggle('species', v)}><Icon name="close" size={11} />{lang === 'en' ? DATA.facets.speciesEn[v] : v}</button>)}
                {filters.use.map((v) => <button key={'u' + v} className="tag-chip on" onClick={() => toggle('use', v)}><Icon name="close" size={11} />{lang === 'en' ? DATA.facets.useEn[v] : v}</button>)}
                {filters.regions.map((v) => <button key={'r' + v} className="tag-chip on" onClick={() => toggle('regions', v)}><Icon name="close" size={11} />{v}</button>)}
                {filters.photographers.map((v) => <button key={'p' + v} className="tag-chip on" onClick={() => toggle('photographers', v)}><Icon name="close" size={11} />{v}</button>)}
                {filters.years.map((v) => <button key={'y' + v} className="tag-chip on" onClick={() => toggle('years', v)}><Icon name="close" size={11} />{v}</button>)}
              </div>
            )}
            {results.length === 0 ? (
              <div className="empty">
                <p className="muted">{pick('Ei tuloksia näillä suodattimilla.', 'No results with these filters.', lang)}</p>
                <button className="btn btn-ghost btn-sm" onClick={clearAll}>{pick('Tyhjennä suodattimet', 'Clear filters', lang)}</button>
              </div>
            ) : (
              <div className="grid grid-browse">
                {results.map((m) => <MediaTile key={m.id} media={m} onOpen={(mm) => onOpenMedia(mm.id, resultIds)} />)}
              </div>
            )}
          </div>
        </div>
      </div>
    </main>
  );
}

Object.assign(window, { Lightbox, Browse });
