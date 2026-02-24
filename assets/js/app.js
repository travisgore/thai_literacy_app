(() => {
  const state = {
    config: null,
    mode: 'syllable_decoder',
    activeItem: null,
    timerStarted: 0,
  };

  const root = document.getElementById('tla-app-root');
  if (!root || typeof TLA_APP === 'undefined') return;

  const toneChoices = ['mid', 'low', 'falling', 'high', 'rising'];

  const fetchConfig = async () => {
    const configUrl = new URL(`${TLA_APP.restUrl}/config`, window.location.origin);
    if (TLA_APP.postId) configUrl.searchParams.set('post_id', String(TLA_APP.postId));
    const response = await fetch(configUrl.toString());
    return response.json();
  };

  const shuffle = (arr) => [...arr].sort(() => Math.random() - 0.5);

  const computeTone = (rules, initialClass, toneMark, syllableType) => {
    const exact = rules.find(
      (r) => r.initial_class === initialClass && r.tone_mark === toneMark && r.syllable_type === syllableType
    );
    if (exact) return exact.resulting_tone;
    const wildcard = rules.find(
      (r) => r.initial_class === initialClass && r.tone_mark === toneMark && r.syllable_type === 'any'
    );
    return wildcard ? wildcard.resulting_tone : 'unknown';
  };

  const recordAttempt = async ({ itemId, mode, correct, errorTags = [], latencyMs = 0 }) => {
    try {
      await fetch(`${TLA_APP.restUrl}/attempt`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': TLA_APP.nonce,
        },
        body: JSON.stringify({
          item_id: itemId,
          mode,
          correct,
          error_tags: errorTags,
          latency_ms: latencyMs,
        }),
      });
    } catch (e) {
      // ignore network errors in UI flow
    }
  };

  const newSyllable = () => {
    const items = state.config.content.syllables;
    state.activeItem = items[Math.floor(Math.random() * items.length)];
    state.timerStarted = performance.now();
  };

  const renderLessonFlow = () => `
    <section class="tla-card">
      <h3>Lesson progression</h3>
      <ol class="tla-lesson-list">
        ${state.config.lesson_flow.map((step) => `<li>${step}</li>`).join('')}
      </ol>
    </section>
  `;

  const renderSyllableDecoder = () => {
    if (!state.activeItem) newSyllable();
    const item = state.activeItem;

    return `
      <section class="tla-card">
        <h3>Syllable decoder</h3>
        <p>Read this syllable and choose its tone.</p>
        <div class="tla-syllable">${item.text}</div>
        <p class="tla-meta">Initial class: <strong>${item.initial_class}</strong> · Tone mark: <strong>${item.tone_mark}</strong> · Type: <strong>${item.syllable_type}</strong></p>
        ${state.config.show_ipa ? `<p class="tla-ipa">Hint IPA: ${item.ipa}</p>` : ''}
        <div class="tla-row">
          ${toneChoices
            .map(
              (tone) => `<button class="tla-btn" data-action="check-tone" data-value="${tone}">${tone}</button>`
            )
            .join('')}
        </div>
        <div id="tla-feedback" class="tla-feedback"></div>
      </section>
    `;
  };

  const renderToneCalculator = () => {
    const syllables = shuffle(state.config.content.syllables).slice(0, 4);
    return `
      <section class="tla-card">
        <h3>Tone calculator trainer</h3>
        <p>Use the decision logic to verify each syllable.</p>
        <div class="tla-grid">
          ${syllables
            .map((item) => {
              const result = computeTone(
                state.config.content.tone_rules,
                item.initial_class,
                item.tone_mark,
                item.syllable_type
              );
              return `
                <article class="tla-subcard">
                  <h4>${item.text}</h4>
                  <p>Class: <strong>${item.initial_class}</strong></p>
                  <p>Mark: <strong>${item.tone_mark}</strong></p>
                  <p>Type: <strong>${item.syllable_type}</strong></p>
                  <p>Computed tone: <strong>${result}</strong></p>
                  <p>Expected: <strong>${item.resulting_tone}</strong></p>
                </article>
              `;
            })
            .join('')}
        </div>
      </section>
    `;
  };

  const renderGraphemeSnap = () => {
    const item = state.config.content.graphemes[Math.floor(Math.random() * state.config.content.graphemes.length)];
    const answers = shuffle([
      item.type,
      ...shuffle(['consonant', 'vowel_pattern', 'tone_mark', 'diacritic']).filter((x) => x !== item.type).slice(0, 2),
    ]);

    state.activeItem = item;

    return `
      <section class="tla-card">
        <h3>Grapheme snap</h3>
        <p>Identify this symbol quickly.</p>
        <div class="tla-syllable">${item.glyph}</div>
        <p class="tla-meta">Hint: ${item.hint || 'No hint available'}</p>
        <div class="tla-row">
          ${answers
            .map((value) => `<button class="tla-btn" data-action="snap" data-value="${value}">${value}</button>`)
            .join('')}
        </div>
        <div id="tla-feedback" class="tla-feedback"></div>
      </section>
    `;
  };

  const renderVowelAssembler = () => {
    const family = state.config.content.vowel_families[Math.floor(Math.random() * state.config.content.vowel_families.length)];

    return `
      <section class="tla-card">
        <h3>Vowel pattern assembler</h3>
        <p>Target IPA family: <strong>${family.target_ipa}</strong></p>
        <p>Choose the matching orthography pattern:</p>
        <div class="tla-row">
          ${shuffle(state.config.content.vowel_families)
            .slice(0, 3)
            .map((f) => `<button class="tla-btn" data-action="vowel" data-value="${f.id}" data-answer="${family.id}">${f.label}</button>`)
            .join('')}
        </div>
        <p class="tla-meta">${family.notes}</p>
        <div id="tla-feedback" class="tla-feedback"></div>
      </section>
    `;
  };

  const render = () => {
    const mode = state.mode;
    const modeView =
      mode === 'tone_calculator'
        ? renderToneCalculator()
        : mode === 'grapheme_snap'
        ? renderGraphemeSnap()
        : mode === 'vowel_assembler'
        ? renderVowelAssembler()
        : renderSyllableDecoder();

    root.innerHTML = `
      <div class="tla-shell">
        <header class="tla-header">
          <h2>${state.config.app_title}</h2>
          <p>Interactive Thai reading practice with configurable content and feature-level feedback.</p>
        </header>

        <section class="tla-card">
          <h3>Practice mode</h3>
          <div class="tla-row">
            <button class="tla-btn ${mode === 'syllable_decoder' ? 'is-active' : ''}" data-action="mode" data-mode="syllable_decoder">Syllable Decoder</button>
            <button class="tla-btn ${mode === 'tone_calculator' ? 'is-active' : ''}" data-action="mode" data-mode="tone_calculator">Tone Calculator</button>
            <button class="tla-btn ${mode === 'grapheme_snap' ? 'is-active' : ''}" data-action="mode" data-mode="grapheme_snap">Grapheme Snap</button>
            <button class="tla-btn ${mode === 'vowel_assembler' ? 'is-active' : ''}" data-action="mode" data-mode="vowel_assembler">Vowel Assembler</button>
          </div>
        </section>

        ${modeView}
        ${renderLessonFlow()}
      </div>
    `;
  };

  root.addEventListener('click', async (event) => {
    const target = event.target.closest('[data-action]');
    if (!target) return;

    const action = target.dataset.action;

    if (action === 'mode') {
      state.mode = target.dataset.mode;
      render();
      return;
    }

    if (action === 'check-tone') {
      const guessed = target.dataset.value;
      const correct = guessed === state.activeItem.resulting_tone;
      const feedback = root.querySelector('#tla-feedback');
      const latency = Math.round(performance.now() - state.timerStarted);
      feedback.textContent = correct
        ? `✅ Correct. ${state.activeItem.text} has ${state.activeItem.resulting_tone} tone (${state.activeItem.meaning}).`
        : `❌ Not quite. Correct tone is ${state.activeItem.resulting_tone}.`;
      await recordAttempt({
        itemId: state.activeItem.id,
        mode: 'syllable_decoder',
        correct,
        errorTags: correct ? [] : ['tone_mismatch'],
        latencyMs: latency,
      });
      newSyllable();
      setTimeout(render, 450);
      return;
    }

    if (action === 'snap') {
      const guessedType = target.dataset.value;
      const correct = guessedType === state.activeItem.type;
      const feedback = root.querySelector('#tla-feedback');
      feedback.textContent = correct
        ? '✅ Correct classification.'
        : `❌ Incorrect. This is ${state.activeItem.type}.`;
      await recordAttempt({
        itemId: state.activeItem.id,
        mode: 'grapheme_snap',
        correct,
        errorTags: correct ? [] : ['grapheme_category'],
      });
      setTimeout(render, 500);
      return;
    }

    if (action === 'vowel') {
      const guessed = target.dataset.value;
      const answer = target.dataset.answer;
      const correct = guessed === answer;
      const feedback = root.querySelector('#tla-feedback');
      feedback.textContent = correct ? '✅ Correct vowel pattern family.' : '❌ Try again.';
      await recordAttempt({
        itemId: answer,
        mode: 'vowel_assembler',
        correct,
        errorTags: correct ? [] : ['vowel_family'],
      });
    }
  });

  const init = async () => {
    root.innerHTML = '<p>Loading Thai literacy app…</p>';
    state.config = await fetchConfig();
    state.mode = state.config.default_mode || 'syllable_decoder';
    newSyllable();
    render();
  };

  init();
})();
