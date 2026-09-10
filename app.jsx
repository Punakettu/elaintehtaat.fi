// app.jsx — router, language + tweak state, mount.

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "colorTheme": "warm",
  "accent": "oxblood",
  "fontPair": "grotesk",
  "albumLayout": "sidebar"
}/*EDITMODE-END*/;

const LS_ROUTE = 'et-route-v1';
const LS_LANG = 'et-lang-v1';

function ProjectsScreen({ nav }) {
  const lang = useLang();
  React.useEffect(() => { window.scrollTo(0, 0); }, []);
  return (
    <main className="shell-wide" style={{ paddingTop: 'clamp(32px, 5vh, 56px)' }}>
      <div className="stack" style={{ gap: 6, marginBottom: 28 }}>
        <div className="eyebrow">{pick('Projektit', 'Projects', lang)}</div>
        <h1 className="display" style={{ fontSize: 30 }}>{pick('Dokumentointiprojektit', 'Documentation projects', lang)}</h1>
      </div>
      <div className="grid proj-grid">
        {DATA.projects.map((p) => {
          const albums = p.albumIds.map((id) => DATA.albumsById[id]);
          return (
            <button key={p.id} className="proj-card" onClick={() => nav({ name: 'browse', project: p.id })}>
              <div className="proj-card-mosaic">
                {albums.slice(0, 3).map((a) => <span key={a.id} style={{ background: tileBg(a.cover.tone) }} />)}
              </div>
              <div className="stack" style={{ gap: 8, padding: '16px 2px 4px' }}>
                <div className="eyebrow">{p.year} · {p.albumIds.length} {pick('albumia', 'albums', lang)}</div>
                <h3 className="display" style={{ fontSize: 19 }}>{pick(p.fi, p.en, lang)}</h3>
                <p className="muted" style={{ fontSize: 13.5 }}>{pick(p.blurbFi, p.blurbEn, lang)}</p>
              </div>
            </button>
          );
        })}
      </div>
    </main>
  );
}

function App() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  const [lang, setLangState] = React.useState(() => localStorage.getItem(LS_LANG) || 'fi');
  const [route, setRoute] = React.useState(() => {
    try { const r = JSON.parse(localStorage.getItem(LS_ROUTE)); if (r && r.name) return r; } catch (e) {}
    return { name: 'home' };
  });
  const [overlay, setOverlay] = React.useState(null); // { mediaId, context }

  React.useEffect(() => { window.applyTheme(t.colorTheme, t.accent, t.fontPair); }, [t.colorTheme, t.accent, t.fontPair]);
  React.useEffect(() => { localStorage.setItem(LS_LANG, lang); }, [lang]);
  React.useEffect(() => { localStorage.setItem(LS_ROUTE, JSON.stringify(route)); }, [route]);

  const nav = React.useCallback((r) => { setOverlay(null); setRoute(r); }, []);
  const setLang = (l) => setLangState(l);
  const openMedia = React.useCallback((mediaId, context) => setOverlay({ mediaId, context: context || null }), []);

  let screen;
  if (route.name === 'album') screen = <AlbumDetail albumId={route.albumId} layout={t.albumLayout} nav={nav} onOpenMedia={(m) => openMedia(m.id, null)} />;
  else if (route.name === 'browse') screen = <Browse route={route} nav={nav} onOpenMedia={openMedia} />;
  else if (route.name === 'projects') screen = <ProjectsScreen nav={nav} />;
  else screen = <Home nav={nav} />;

  return (
    <Lang.Provider value={lang}>
      <div className="app">
        <Masthead route={route} lang={lang} setLang={setLang} nav={nav} />
        {screen}
        <Footer lang={lang} nav={nav} />
      </div>

      {overlay && (
        <Lightbox
          mediaId={overlay.mediaId}
          context={overlay.context}
          onClose={() => setOverlay(null)}
          onOpenMedia={openMedia}
          nav={nav}
        />
      )}

      <TweaksPanel title="Tweaks">
        <TweakSection label={pick('Väri & sävy', 'Color & tone', lang)} />
        <TweakRadio label={pick('Sävy', 'Tone', lang)} value={t.colorTheme}
          options={[{ value: 'warm', label: 'Warm' }, { value: 'cool', label: 'Cool' }, { value: 'ink', label: 'Ink' }]}
          onChange={(v) => setTweak('colorTheme', v)} />
        <TweakRadio label={pick('Korostus', 'Accent', lang)} value={t.accent}
          options={[{ value: 'oxblood', label: 'Oxblood' }, { value: 'ochre', label: 'Ochre' }, { value: 'bone', label: 'None' }]}
          onChange={(v) => setTweak('accent', v)} />
        <TweakSection label={pick('Typografia', 'Typography', lang)} />
        <TweakRadio label={pick('Kirjasinpari', 'Type pairing', lang)} value={t.fontPair}
          options={[{ value: 'grotesk', label: 'Grotesk' }, { value: 'bricolage', label: 'Bricolage' }, { value: 'archivo', label: 'Archivo' }]}
          onChange={(v) => setTweak('fontPair', v)} />
        <TweakSection label={pick('Albumisivu', 'Album page', lang)} />
        <TweakRadio label={pick('Asettelu', 'Layout', lang)} value={t.albumLayout}
          options={[{ value: 'sidebar', label: 'Sidebar' }, { value: 'stacked', label: 'Stacked' }, { value: 'immersive', label: 'Immersive' }]}
          onChange={(v) => setTweak('albumLayout', v)} />
        {route.name !== 'album' && (
          <TweakButton label={pick('Avaa esimerkkialbumi', 'Open sample album', lang)} secondary onClick={() => nav({ name: 'album', albumId: 'a-jokioinen' })} />
        )}
      </TweaksPanel>
    </Lang.Provider>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
