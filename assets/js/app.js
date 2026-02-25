(() => {
  const initializedRoots = new WeakSet();

  const stageOrder = ['vowel', 'consonant', 'tone', 'pronunciation'];

  const stageLabels = {
    vowel: 'Vowel',
    consonant: 'Consonant',
    tone: 'Tone',
    pronunciation: 'Pronunciation',
  };

  const toneChoices = [
    { value: 'mid', label: 'Flat' },
    { value: 'low', label: 'Low' },
    { value: 'falling', label: 'Falling' },
    { value: 'high', label: 'High' },
    { value: 'rising', label: 'Rising' },
    { value: 'unknown', label: 'Unknown' },
  ];

  const masteryStreakTarget = 3;

  const emptyStageStats = () => ({
    attempts: 0,
    correct: 0,
    wrong: 0,
    totalLatencyMs: 0,
  });

  const buildEmptyStats = () => ({
    attempts: 0,
    correct: 0,
    wrong: 0,
    totalLatencyMs: 0,
    fastestMs: 0,
    slowestMs: 0,
    currentCorrectStreak: 0,
    bestCorrectStreak: 0,
    byStage: stageOrder.reduce((acc, stage) => {
      acc[stage] = emptyStageStats();
      return acc;
    }, {}),
  });

  const sanitizeStats = (raw) => {
    const base = buildEmptyStats();
    const source = raw && typeof raw === 'object' ? raw : {};

    base.attempts = Number.isFinite(source.attempts) ? Math.max(0, Math.floor(source.attempts)) : 0;
    base.correct = Number.isFinite(source.correct) ? Math.max(0, Math.floor(source.correct)) : 0;
    base.wrong = Number.isFinite(source.wrong) ? Math.max(0, Math.floor(source.wrong)) : 0;
    base.totalLatencyMs = Number.isFinite(source.totalLatencyMs) ? Math.max(0, Math.floor(source.totalLatencyMs)) : 0;
    base.fastestMs = Number.isFinite(source.fastestMs) ? Math.max(0, Math.floor(source.fastestMs)) : 0;
    base.slowestMs = Number.isFinite(source.slowestMs) ? Math.max(0, Math.floor(source.slowestMs)) : 0;
    base.currentCorrectStreak = Number.isFinite(source.currentCorrectStreak)
      ? Math.max(0, Math.floor(source.currentCorrectStreak))
      : 0;
    base.bestCorrectStreak = Number.isFinite(source.bestCorrectStreak) ? Math.max(0, Math.floor(source.bestCorrectStreak)) : 0;

    const stageSource = source.byStage && typeof source.byStage === 'object' ? source.byStage : {};
    stageOrder.forEach((stage) => {
      const stageRow = stageSource[stage] && typeof stageSource[stage] === 'object' ? stageSource[stage] : {};
      base.byStage[stage] = {
        attempts: Number.isFinite(stageRow.attempts) ? Math.max(0, Math.floor(stageRow.attempts)) : 0,
        correct: Number.isFinite(stageRow.correct) ? Math.max(0, Math.floor(stageRow.correct)) : 0,
        wrong: Number.isFinite(stageRow.wrong) ? Math.max(0, Math.floor(stageRow.wrong)) : 0,
        totalLatencyMs: Number.isFinite(stageRow.totalLatencyMs) ? Math.max(0, Math.floor(stageRow.totalLatencyMs)) : 0,
      };
    });

    return base;
  };

  const toneLabel = (tone) => {
    const entry = toneChoices.find((choice) => choice.value === tone);
    return entry ? entry.label : tone || 'Unknown';
  };

  const prettyValue = (value) => String(value || '').replace(/_/g, ' ');

  const consonantClassLabel = (value) => {
    const labels = {
      high: 'High class',
      mid: 'Middle class',
      low: 'Low class',
    };
    return labels[value] || prettyValue(value);
  };

  const markerLabel = (marker) => {
    if (!marker) return 'No tone mark';
    return marker.label || marker.id || 'Tone marker';
  };

  const markerGlyph = (marker) => {
    if (!marker || marker.id === 'none') return 'none';
    return marker.glyph || marker.id || 'marker';
  };

  const consonantTheme = (value) => {
    if (value === 'high' || value === 'mid' || value === 'low') {
      return value;
    }
    return 'neutral';
  };

  const esc = (value) =>
    String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

  const randomItem = (arr) => (arr.length ? arr[Math.floor(Math.random() * arr.length)] : null);

  const shuffle = (arr) => [...arr].sort(() => Math.random() - 0.5);

  const slugify = (value) =>
    String(value || '')
      .toLowerCase()
      .replace(/[^a-z0-9_]+/g, '_')
      .replace(/^_+|_+$/g, '');

  const composeWord = (consonant, vowel, marker) => {
    const consonantGlyph = consonant && consonant.glyph ? consonant.glyph : '';
    const pattern = vowel && vowel.pattern ? vowel.pattern : '';
    const markerGlyphValue = marker && marker.id !== 'none' ? marker.glyph || '' : '';

    if (!consonantGlyph && !pattern) {
      return markerGlyphValue;
    }

    const base = pattern.includes('◌') ? pattern.replace(/◌/g, consonantGlyph) : `${consonantGlyph}${pattern}`;
    return `${base}${markerGlyphValue}`;
  };

  const buildMapById = (rows) => {
    const map = {};
    rows.forEach((row) => {
      if (row && row.id) {
        map[row.id] = row;
      }
    });
    return map;
  };

  const normalizeArrayRows = (rows, fields) =>
    (Array.isArray(rows) ? rows : []).map((row) => {
      const source = row && typeof row === 'object' ? { ...row } : {};
      fields.forEach((field) => {
        if (typeof source[field] !== 'string') {
          if (source[field] === undefined || source[field] === null) {
            source[field] = '';
          } else {
            source[field] = String(source[field]);
          }
        }
      });
      return source;
    });

  const markerDefaults = {
    none: { label: 'No tone mark', glyph: '', defaultTone: 'mid' },
    mai_ek: { label: 'Mai Ek', glyph: '่', defaultTone: 'low' },
    mai_tho: { label: 'Mai Tho', glyph: '้', defaultTone: 'falling' },
    mai_tri: { label: 'Mai Tri', glyph: '๊', defaultTone: 'high' },
    mai_chattawa: { label: 'Mai Chattawa', glyph: '๋', defaultTone: 'rising' },
  };

  const markerFromId = (markerId) => {
    const normalized = String(markerId || 'none');
    const defaults = markerDefaults[normalized] || { label: normalized, glyph: '', defaultTone: '' };
    return {
      id: normalized,
      label: defaults.label,
      glyph: defaults.glyph,
      default_tone: defaults.defaultTone,
      hint: '',
    };
  };

  const migrateLegacyContent = (rawContent) => {
    const content = rawContent && typeof rawContent === 'object' ? { ...rawContent } : {};

    content.items = normalizeArrayRows(content.items, [
      'id',
      'word_text',
      'tone_result',
      'pronunciation',
      'description',
      'audio_url',
      'vowel_character',
      'vowel_name',
      'vowel_sound',
      'vowel_audio_url',
      'vowel_note',
      'consonant_character',
      'consonant_name',
      'consonant_sound',
      'consonant_class',
      'consonant_note',
      'tone_marker_id',
      'tone_marker_name',
      'tone_marker_character',
      'tone_marker_note',
    ]);

    content.vowels = normalizeArrayRows(content.vowels, ['id', 'label', 'pattern', 'with_aw_ang', 'sound', 'audio_url', 'hint']);
    content.consonants = normalizeArrayRows(content.consonants, ['id', 'glyph', 'name', 'class', 'sound', 'hint']);
    content.tone_markers = normalizeArrayRows(content.tone_markers, ['id', 'label', 'glyph', 'default_tone', 'hint']);
    content.combinations = normalizeArrayRows(content.combinations, [
      'id',
      'consonant_id',
      'vowel_id',
      'tone_marker_id',
      'text',
      'tone',
      'pronunciation',
      'description',
      'audio_url',
      'consonant_class',
    ]);

    if (!content.vowels.length && Array.isArray(content.vowel_families)) {
      content.vowels = content.vowel_families.map((item) => ({
        id: String(item.id || ''),
        label: String(item.label || item.id || ''),
        pattern: String(item.label || ''),
        with_aw_ang: '',
        sound: String(item.target_ipa || ''),
        audio_url: '',
        hint: String(item.notes || ''),
      }));
    }

    if (!content.consonants.length && Array.isArray(content.graphemes)) {
      content.consonants = content.graphemes
        .filter((item) => item && item.type === 'consonant')
        .map((item) => ({
          id: String(item.id || ''),
          glyph: String(item.glyph || ''),
          name: String(item.hint || item.id || ''),
          class: String(item.class || 'mid'),
          sound: String(item.ipa_initial || ''),
          hint: String(item.hint || ''),
        }));
    }

    if (!content.tone_markers.length && Array.isArray(content.graphemes)) {
      content.tone_markers = content.graphemes
        .filter((item) => item && item.type === 'tone_mark')
        .map((item) => ({
          id: String(item.id || ''),
          label: String(item.hint || item.id || ''),
          glyph: String(item.glyph || ''),
          default_tone: '',
          hint: String(item.hint || ''),
        }));
    }

    if (!content.tone_markers.some((item) => item && item.id === 'none')) {
      content.tone_markers.unshift({
        id: 'none',
        label: 'No tone mark',
        glyph: '',
        default_tone: 'mid',
        hint: '',
      });
    }

    if (!content.combinations.length && Array.isArray(content.syllables)) {
      content.combinations = content.syllables.map((item) => ({
        id: String(item.id || ''),
        consonant_id: '',
        vowel_id: '',
        tone_marker_id: String(item.tone_mark || 'none'),
        text: String(item.text || ''),
        tone: String(item.resulting_tone || 'unknown'),
        pronunciation: String(item.ipa || ''),
        description: String(item.meaning || ''),
        audio_url: String(item.audio_url || ''),
        consonant_class: String(item.initial_class || ''),
      }));
    }

    return content;
  };

  const hydrateUnifiedItem = (raw, index) => {
    const item = raw && typeof raw === 'object' ? { ...raw } : {};

    const hasConsonantData = !!(
      item.consonant_character ||
      item.consonant_name ||
      item.consonant_sound ||
      item.consonant_note ||
      item.consonant_class
    );
    const hasVowelData = !!(item.vowel_character || item.vowel_name || item.vowel_sound || item.vowel_note);

    const consonant = {
      id:
        item.consonant_id ||
        (hasConsonantData
          ? `cons_${slugify(item.consonant_name || item.consonant_character || item.id || index + 1)}`
          : ''),
      glyph: item.consonant_character || '',
      name: item.consonant_name || item.consonant_character || '',
      class: item.consonant_class || '',
      sound: item.consonant_sound || '',
      hint: item.consonant_note || '',
    };

    const vowel = {
      id:
        item.vowel_id ||
        (hasVowelData ? `vowel_${slugify(item.vowel_name || item.vowel_character || item.id || index + 1)}` : ''),
      label: item.vowel_name || item.vowel_character || '',
      pattern: item.vowel_character || '',
      with_aw_ang: item.vowel_character || '',
      sound: item.vowel_sound || '',
      audio_url: item.vowel_audio_url || '',
      hint: item.vowel_note || '',
    };

    const markerBase = markerFromId(item.tone_marker_id || 'none');
    const marker = {
      ...markerBase,
      label: item.tone_marker_name || markerBase.label,
      glyph: item.tone_marker_character || markerBase.glyph,
      hint: item.tone_marker_note || '',
    };

    const text = item.word_text || composeWord(consonant, vowel, marker);

    return {
      id: item.id || `item_${index + 1}`,
      consonant,
      vowel,
      marker,
      consonant_class: consonant.class || '',
      text,
      tone: item.tone_result || item.tone || marker.default_tone || 'unknown',
      pronunciation: item.pronunciation || '',
      description: item.description || '',
      audio_url: item.audio_url || '',
    };
  };

  const normalizeConfig = (config) => {
    const normalized = config && typeof config === 'object' ? { ...config } : {};
    normalized.content = migrateLegacyContent(normalized.content);
    normalized.app_title = normalized.app_title || 'Learn to Read Thai';
    normalized.show_ipa = !!normalized.show_ipa;
    return normalized;
  };

  const initRoot = (root) => {
    if (!root || initializedRoots.has(root)) return;
    initializedRoots.add(root);

    const fallback = window.TLA_APP && typeof window.TLA_APP === 'object' ? window.TLA_APP : {};
    const restBase = (root.dataset.restUrl || fallback.restUrl || '').replace(/\/$/, '');
    const nonce = root.dataset.nonce || fallback.nonce || '';
    const postId = Number.parseInt(root.dataset.postId || fallback.postId || '0', 10) || 0;

    if (!restBase) {
      root.innerHTML = '<p>The game could not load right now.</p>';
      return;
    }

    const storageKey = `tla_srs_progress_${postId || 'default'}`;

    const state = {
      config: null,
      combinations: [],
      combinationById: {},
      vowelPool: [],
      consonantPool: [],
      vowelSoundAudioMap: {},
      consonantSoundAudioMap: {},
      cards: [],
      round: null,
      phase: 'learn',
      feedback: null,
      selectedChoice: '',
      timerStarted: 0,
      turn: 0,
      progress: {},
      lastStage: '',
      stats: buildEmptyStats(),
      showStats: false,
      keepPracticing: false,
    };

    const fetchConfig = async () => {
      const configUrl = new URL(`${restBase}/config`, window.location.origin);
      if (postId) {
        configUrl.searchParams.set('post_id', String(postId));
      }

      const response = await fetch(configUrl.toString(), { credentials: 'same-origin' });
      if (!response.ok) {
        throw new Error(`Config request failed (${response.status})`);
      }

      return normalizeConfig(await response.json());
    };

    const recordAttempt = async ({ itemId, mode, correct, errorTags = [], latencyMs = 0 }) => {
      try {
        const headers = { 'Content-Type': 'application/json' };
        if (nonce) {
          headers['X-WP-Nonce'] = nonce;
        }

        await fetch(`${restBase}/attempt`, {
          method: 'POST',
          credentials: 'same-origin',
          headers,
          body: JSON.stringify({
            item_id: itemId,
            mode,
            correct,
            error_tags: errorTags,
            latency_ms: latencyMs,
          }),
        });
      } catch (_error) {
        // Keep learner flow running even if analytics save fails.
      }
    };

    const loadSrs = () => {
      try {
        const raw = window.localStorage.getItem(storageKey);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
          state.turn = Number.isInteger(parsed.turn) ? parsed.turn : 0;
          state.lastStage = typeof parsed.lastStage === 'string' ? parsed.lastStage : '';
          state.progress = parsed.progress && typeof parsed.progress === 'object' ? parsed.progress : {};
          state.stats = sanitizeStats(parsed.stats);
        }
      } catch (_error) {
        state.turn = 0;
        state.progress = {};
        state.stats = buildEmptyStats();
      }
    };

    const saveSrs = () => {
      try {
        window.localStorage.setItem(
          storageKey,
          JSON.stringify({
            turn: state.turn,
            lastStage: state.lastStage,
            progress: state.progress,
            stats: state.stats,
          })
        );
      } catch (_error) {
        // Ignore storage write errors.
      }
    };

    let previewAudio = null;
    const playPreviewAudio = (audioUrl) => {
      const source = typeof audioUrl === 'string' ? audioUrl.trim() : '';
      if (!source) return;

      try {
        if (previewAudio) {
          previewAudio.pause();
          previewAudio.currentTime = 0;
        }

        previewAudio = new Audio(source);
        previewAudio.play().catch(() => {
          // Ignore autoplay/playback errors.
        });
      } catch (_error) {
        // Ignore preview playback failures.
      }
    };

    const ensureProgress = (cardId) => {
      if (!state.progress[cardId] || typeof state.progress[cardId] !== 'object') {
        state.progress[cardId] = {
          interval: 0,
          nextTurn: 0,
          streak: 0,
          needsLearn: true,
        };
      }

      const slot = state.progress[cardId];
      if (!Number.isInteger(slot.interval) || slot.interval < 0) slot.interval = 0;
      if (!Number.isInteger(slot.nextTurn) || slot.nextTurn < 0) slot.nextTurn = 0;
      if (!Number.isInteger(slot.streak) || slot.streak < 0) slot.streak = 0;
      if (typeof slot.needsLearn !== 'boolean') {
        slot.needsLearn = slot.streak <= 0;
      }
      return slot;
    };

    const hydrateCombination = (raw, lookups) => {
      const combination = raw && typeof raw === 'object' ? { ...raw } : {};
      const consonant = lookups.consonantsById[combination.consonant_id] || null;
      const vowel = lookups.vowelsById[combination.vowel_id] || null;
      const marker = lookups.markersById[combination.tone_marker_id] || lookups.markersById.none || null;

      const consonantClass =
        combination.consonant_class || (consonant && consonant.class) || (combination.initial_class || '');

      return {
        id: combination.id || '',
        consonant,
        vowel,
        marker,
        consonant_class: consonantClass,
        text: combination.text || composeWord(consonant, vowel, marker),
        tone: combination.tone || (marker && marker.default_tone) || 'unknown',
        pronunciation: combination.pronunciation || combination.ipa || '',
        description: combination.description || combination.meaning || '',
        audio_url: combination.audio_url || '',
      };
    };

    const buildCards = () => {
      const cards = [];

      state.combinations.forEach((combo) => {
        if (!combo.id) return;

        if (combo.vowel && combo.vowel.id) {
          cards.push({ id: `vowel:${combo.id}`, stage: 'vowel', comboId: combo.id });
        }

        if (combo.consonant && combo.consonant.id && combo.consonant.sound) {
          cards.push({ id: `consonant:${combo.id}`, stage: 'consonant', comboId: combo.id });
        }

        cards.push({ id: `tone:${combo.id}`, stage: 'tone', comboId: combo.id });
        cards.push({ id: `pronunciation:${combo.id}`, stage: 'pronunciation', comboId: combo.id });
      });

      return cards;
    };

    const getStageIndex = (stage) => stageOrder.indexOf(stage);

    const pickNextCard = () => {
      if (!state.cards.length) return null;

      const scored = state.cards.map((card) => ({
        card,
        progress: ensureProgress(card.id),
      }));

      let pool = scored.filter((entry) => entry.progress.nextTurn <= state.turn);

      if (!pool.length) {
        const minDue = Math.min(...scored.map((entry) => entry.progress.nextTurn));
        pool = scored.filter((entry) => entry.progress.nextTurn === minDue);
      }

      if (state.lastStage) {
        const interleaved = pool.filter((entry) => entry.card.stage !== state.lastStage);
        if (interleaved.length) {
          pool = interleaved;
        }
      }

      pool.sort((a, b) => {
        if (a.progress.nextTurn !== b.progress.nextTurn) {
          return a.progress.nextTurn - b.progress.nextTurn;
        }
        return getStageIndex(a.card.stage) - getStageIndex(b.card.stage);
      });

      const first = pool[0];
      if (!first) return null;

      const tied = pool.filter((entry) => entry.progress.nextTurn === first.progress.nextTurn);
      const selected = randomItem(tied);
      return selected ? selected.card : first.card;
    };

    const buildRound = (card) => {
      const combo = state.combinationById[card.comboId];
      if (!combo) return null;

      if (card.stage === 'vowel') {
        if (!combo.vowel || !combo.vowel.sound) return null;

        const correctSound = String(combo.vowel.sound || '').trim();
        if (!correctSound) return null;

        const uniqueSounds = Array.from(
          new Set(
            state.vowelPool
              .map((row) => String(row.sound || '').trim())
              .filter((value) => value.length > 0)
          )
        );
        if (!uniqueSounds.includes(correctSound)) {
          uniqueSounds.push(correctSound);
        }

        const distractors = shuffle(uniqueSounds.filter((value) => value !== correctSound)).slice(0, 3);
        const options = shuffle([correctSound, ...distractors]).map((value) => ({
          value,
          label: value,
          audio_url: state.vowelSoundAudioMap[value] || '',
        }));

        return {
          ...card,
          combo,
          answer: correctSound,
          options,
          mode: 'srs_vowel',
        };
      }

      if (card.stage === 'consonant') {
        if (!combo.consonant || !combo.consonant.id || !combo.consonant.sound) return null;

        const uniqueSounds = Array.from(
          new Set(
            state.consonantPool
              .map((row) => String(row.sound || '').trim())
              .filter((value) => value.length > 0)
          )
        );

        const correctSound = String(combo.consonant.sound || '').trim();
        if (!correctSound) return null;

        const distractors = shuffle(uniqueSounds.filter((value) => value !== correctSound)).slice(0, 3);
        const options = shuffle([correctSound, ...distractors]).map((value) => ({
          value,
          label: value,
          audio_url: state.consonantSoundAudioMap[value] || '',
        }));

        return {
          ...card,
          combo,
          answer: correctSound,
          options,
          mode: 'srs_consonant',
        };
      }

      if (card.stage === 'tone') {
        return {
          ...card,
          combo,
          answer: combo.tone || 'unknown',
          options: toneChoices.map((choice) => ({ value: choice.value, label: choice.label })),
          mode: 'srs_tone',
        };
      }

      if (card.stage === 'pronunciation') {
        const pool = state.combinations.filter((entry) => entry.id);

        const distractors = shuffle(pool.filter((entry) => entry.id !== combo.id)).slice(0, 3);
        const options = shuffle([combo, ...distractors]).map((entry) => ({
          value: entry.id,
          pronunciation: entry.pronunciation || entry.text || '(No pronunciation text)',
          description: entry.description || '',
          audio_url: entry.audio_url || '',
        }));

        return {
          ...card,
          combo,
          answer: combo.id,
          options,
          mode: 'srs_pronunciation',
        };
      }

      return null;
    };

    const prepareRound = () => {
      state.feedback = null;
      state.selectedChoice = '';
      state.timerStarted = 0;

      if (!state.cards.length) {
        state.round = null;
        return false;
      }

      for (let i = 0; i < state.cards.length; i += 1) {
        const candidate = pickNextCard();
        if (!candidate) break;

        const round = buildRound(candidate);
        if (round) {
          state.round = round;
          const slot = ensureProgress(round.id);
          if (slot.needsLearn) {
            state.phase = 'learn';
          } else {
            state.phase = 'test';
            state.timerStarted = performance.now();
          }
          return true;
        }

        const slot = ensureProgress(candidate.id);
        slot.nextTurn = state.turn + 1;
      }

      state.round = null;
      return false;
    };

    const applySrsResult = (cardId, correct) => {
      const slot = ensureProgress(cardId);

      if (correct) {
        slot.streak += 1;
        slot.interval = slot.interval <= 0 ? 2 : Math.min(Math.round(slot.interval * 1.8), 40);
        slot.needsLearn = false;
      } else {
        slot.streak = 0;
        slot.interval = 1;
        slot.needsLearn = true;
      }

      slot.nextTurn = state.turn + slot.interval;
    };

    const applyLocalStats = (stage, correct, latencyMs) => {
      const safeLatency = Number.isFinite(latencyMs) ? Math.max(0, Math.floor(latencyMs)) : 0;
      const stageKey = stageOrder.includes(stage) ? stage : stageOrder[0];

      state.stats.attempts += 1;
      state.stats.totalLatencyMs += safeLatency;
      if (safeLatency > 0 && (!state.stats.fastestMs || safeLatency < state.stats.fastestMs)) {
        state.stats.fastestMs = safeLatency;
      }
      if (safeLatency > state.stats.slowestMs) {
        state.stats.slowestMs = safeLatency;
      }

      if (correct) {
        state.stats.correct += 1;
        state.stats.currentCorrectStreak += 1;
        state.stats.bestCorrectStreak = Math.max(state.stats.bestCorrectStreak, state.stats.currentCorrectStreak);
      } else {
        state.stats.wrong += 1;
        state.stats.currentCorrectStreak = 0;
      }

      if (!state.stats.byStage[stageKey]) {
        state.stats.byStage[stageKey] = emptyStageStats();
      }
      const row = state.stats.byStage[stageKey];
      row.attempts += 1;
      row.totalLatencyMs += safeLatency;
      if (correct) {
        row.correct += 1;
      } else {
        row.wrong += 1;
      }
    };

    const getMasterySnapshot = () => {
      const total = state.cards.length;
      if (!total) {
        return {
          percent: 0,
          masteredCount: 0,
          totalCount: 0,
          targetStreak: masteryStreakTarget,
          isComplete: false,
        };
      }

      let points = 0;
      let masteredCount = 0;

      state.cards.forEach((card) => {
        const slot = ensureProgress(card.id);
        const streak = Number.isInteger(slot.streak) ? Math.max(slot.streak, 0) : 0;
        points += Math.min(streak, masteryStreakTarget);
        if (streak >= masteryStreakTarget) {
          masteredCount += 1;
        }
      });

      const percent = Math.max(0, Math.min(100, Math.round((points / (total * masteryStreakTarget)) * 100)));
      return {
        percent,
        masteredCount,
        totalCount: total,
        targetStreak: masteryStreakTarget,
        isComplete: masteredCount >= total,
      };
    };

    const renderWord = (combo, options = {}) => {
      const audioUrl = typeof options.audioUrl === 'string' ? options.audioUrl.trim() : '';
      const isClickable = !!options.clickable && !!audioUrl;
      const groupClass = `tla-group-${consonantTheme(combo.consonant_class)}`;
      const clickClass = isClickable ? ' tla-click-audio' : '';
      if (isClickable) {
        return `<button type="button" class="tla-syllable tla-syllable-btn ${groupClass}${clickClass}" data-action="play-audio" data-audio="${esc(
          audioUrl
        )}" aria-label="Play word audio"><span class="tla-thai">${esc(combo.text || '')}</span></button>`;
      }
      return `<div class="tla-syllable ${groupClass}"><span class="tla-thai">${esc(combo.text || '')}</span></div>`;
    };

    const renderLearnSymbol = (text, className = 'tla-group-neutral', audioUrl = '') => {
      const source = String(audioUrl || '').trim();
      const isClickable = !!source;
      const clickClass = isClickable ? ' tla-click-audio' : '';
      if (isClickable) {
        return `<button type="button" class="tla-syllable tla-syllable-btn ${className}${clickClass}" data-action="play-audio" data-audio="${esc(
          source
        )}" aria-label="Play audio"><span class="tla-thai tla-vowel-text">${esc(text || '')}</span></button>`;
      }
      return `<p class="tla-syllable ${className}"><span class="tla-thai tla-vowel-text">${esc(text || '')}</span></p>`;
    };

    const renderWordExampleHint = (combo, audioUrl = '') => {
      const source = String(audioUrl || '').trim();
      if (!source) {
        return `<p class="tla-word-example"><strong>Word Example:</strong> ${esc(combo.text || '')}</p>`;
      }
      return `
        <button
          type="button"
          class="tla-word-example tla-word-example-play"
          data-action="play-audio"
          data-audio="${esc(source)}"
        >
          <strong>Word Example:</strong> ${esc(combo.text || '')}
          <span class="tla-word-example-tip">Tap to play audio</span>
        </button>
      `;
    };

    const renderStageProgress = () =>
      `<p class="tla-srs-note">Interleaved SRS review is active. The app chooses the next card automatically.</p>`;

    const renderProgressBar = (mastery) => `
      <section class="tla-card tla-progress-card">
        <div class="tla-progress-head">
          <h3>Section progress</h3>
          <p>${mastery.masteredCount}/${mastery.totalCount} items mastered (${mastery.percent}%)</p>
        </div>
        <div class="tla-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${mastery.percent}">
          <div class="tla-progress-fill" style="width:${mastery.percent}%"></div>
        </div>
        <p class="tla-progress-note">Goal: get each item right ${mastery.targetStreak} times in a row.</p>
      </section>
    `;

    const renderColorLegend = () => `
      <section class="tla-card tla-legend-card">
        <h3>Color guide</h3>
        <p>Consonant classes stay color coded to help pattern recognition.</p>
        <div class="tla-legend-grid">
          <span class="tla-badge tla-group-high">High class</span>
          <span class="tla-badge tla-group-mid">Middle class</span>
          <span class="tla-badge tla-group-low">Low class</span>
        </div>
      </section>
    `;

    const renderRoundMeta = () => `<p class="tla-meta"><strong>SRS turn:</strong> ${state.turn}</p>`;

    const formatLatency = (ms) => {
      if (!ms) return 'n/a';
      return `${(ms / 1000).toFixed(1)}s`;
    };

    const renderStatsBack = (mastery) => {
      const attempts = state.stats.attempts || 0;
      const accuracy = attempts ? Math.round((state.stats.correct / attempts) * 100) : 0;
      const avgMs = attempts ? Math.round(state.stats.totalLatencyMs / attempts) : 0;

      return `
        <section class="tla-card tla-stats-card">
          <div class="tla-card-lead">
            <span class="tla-stage-context-pill">Stats</span>
            <div class="tla-card-lead-copy">
              <h3>Your learning stats</h3>
              <p>Track results, speed, and mastery progress.</p>
            </div>
          </div>

          <div class="tla-stats-grid">
            <div class="tla-stats-item"><strong>${attempts}</strong><span>Total answers</span></div>
            <div class="tla-stats-item"><strong>${state.stats.correct}</strong><span>Correct</span></div>
            <div class="tla-stats-item"><strong>${state.stats.wrong}</strong><span>Wrong</span></div>
            <div class="tla-stats-item"><strong>${accuracy}%</strong><span>Accuracy</span></div>
            <div class="tla-stats-item"><strong>${formatLatency(avgMs)}</strong><span>Average speed</span></div>
            <div class="tla-stats-item"><strong>${formatLatency(state.stats.fastestMs)}</strong><span>Fastest answer</span></div>
            <div class="tla-stats-item"><strong>${formatLatency(state.stats.slowestMs)}</strong><span>Slowest answer</span></div>
            <div class="tla-stats-item"><strong>${state.stats.bestCorrectStreak}</strong><span>Best correct streak</span></div>
            <div class="tla-stats-item"><strong>${mastery.masteredCount}/${mastery.totalCount}</strong><span>Mastered items</span></div>
          </div>

          <div class="tla-stage-stats">
            ${stageOrder
              .map((stage) => {
                const row = state.stats.byStage[stage] || emptyStageStats();
                const stageAccuracy = row.attempts ? Math.round((row.correct / row.attempts) * 100) : 0;
                const stageAvg = row.attempts ? Math.round(row.totalLatencyMs / row.attempts) : 0;
                return `
                  <div class="tla-stage-stats-row">
                    <strong>${esc(stageLabels[stage])}</strong>
                    <span>${row.correct}/${row.attempts} correct (${stageAccuracy}%)</span>
                    <span>Avg ${formatLatency(stageAvg)}</span>
                  </div>
                `;
              })
              .join('')}
          </div>

          <button type="button" class="tla-btn tla-primary-btn" data-action="show-game">Back to game</button>
        </section>
      `;
    };

    const renderCompletionCard = () => `
      <section class="tla-card tla-complete-card">
        <h3>Congratulations!</h3>
        <p>You completed this section with strong mastery.</p>
        <p>Move on to the next section when you are ready.</p>
        <button type="button" class="tla-btn tla-primary-btn" data-action="continue-practice">Continue practicing here</button>
      </section>
    `;

    const renderCardLead = (title, subtitle) => {
      const stage = state.round ? state.round.stage : '';
      const stageIndex = Math.max(stageOrder.indexOf(stage), 0) + 1;
      const stageLabel = stage ? stageLabels[stage] : 'Skill';
      return `
        <div class="tla-card-lead">
          <span class="tla-stage-context-pill">${stageIndex}. ${esc(stageLabel)}</span>
          <div class="tla-card-lead-copy">
            <h3>${esc(title)}</h3>
            ${subtitle ? `<p>${esc(subtitle)}</p>` : ''}
          </div>
        </div>
      `;
    };

    const renderLearnCard = () => {
      if (!state.round) {
        return `
          <section class="tla-card">
            <h3>No training cards available</h3>
            <p>Add testing items in the post editor first.</p>
          </section>
        `;
      }

      const combo = state.round.combo;

      if (state.round.stage === 'vowel') {
        return `
          <section class="tla-card tla-card-learn">
            ${renderCardLead('Step 1: Learn the answer', 'อ (aw ang) is used as a placeholder when there is no consonant.')}
            ${renderLearnSymbol(
              combo.vowel.with_aw_ang || combo.vowel.pattern || combo.vowel.label,
              'tla-group-neutral',
              combo.vowel.audio_url || combo.audio_url
            )}
            ${renderWord(combo, { clickable: true, audioUrl: combo.audio_url })}
            <p class="tla-meta"><strong>Vowel:</strong> ${esc(combo.vowel.with_aw_ang || combo.vowel.pattern || combo.vowel.label || combo.vowel.id)}</p>
            <p class="tla-meta"><strong>Word Example:</strong> ${esc(combo.text || '')}</p>
            ${combo.vowel.sound ? `<p class="tla-meta"><strong>Sound:</strong> ${esc(combo.vowel.sound)}</p>` : ''}
            ${combo.vowel.hint ? `<p class="tla-explanation">${esc(combo.vowel.hint)}</p>` : ''}
            <button type="button" class="tla-btn tla-primary-btn" data-action="start-test">Start test</button>
          </section>
        `;
      }

      if (state.round.stage === 'consonant') {
        const consonantOnly = combo.consonant && combo.consonant.glyph ? combo.consonant.glyph : '';
        const consonantClassName = `tla-group-${consonantTheme(combo.consonant ? combo.consonant.class : '')}`;
        return `
          <section class="tla-card tla-card-learn">
            ${renderCardLead('Step 1: Learn the answer', 'Try to memorize the pronounciation and class of this consonant')}
            ${renderLearnSymbol(consonantOnly, consonantClassName, combo.audio_url)}
            ${renderWordExampleHint(combo, combo.audio_url)}
            <p class="tla-meta"><strong>Consonant sound:</strong> ${esc(combo.consonant.sound || '(not set)')}</p>
            <p class="tla-meta"><strong>Consonant class:</strong> ${esc(consonantClassLabel(combo.consonant.class) || 'n/a')}</p>
            ${combo.consonant.hint ? `<p class="tla-explanation">${esc(combo.consonant.hint)}</p>` : ''}
            <button type="button" class="tla-btn tla-primary-btn" data-action="start-test">Start test</button>
          </section>
        `;
      }

      if (state.round.stage === 'tone') {
        return `
          <section class="tla-card tla-card-learn">
            ${renderCardLead('Step 1: Learn the answer', 'Review the word parts before identifying the tone.')}
            ${renderWord(combo, { clickable: true, audioUrl: combo.audio_url })}
            <div class="tla-badge-row">
              <span class="tla-badge">Vowel: ${esc(combo.vowel ? combo.vowel.label || combo.vowel.id : 'n/a')}</span>
              <span class="tla-badge">Consonant: ${esc(combo.consonant ? combo.consonant.glyph : 'n/a')}</span>
              <span class="tla-badge">Consonant class: ${esc(
                consonantClassLabel(combo.consonant ? combo.consonant.class : '') || 'n/a'
              )}</span>
              <span class="tla-badge">Tone marker: ${esc(markerLabel(combo.marker))} (${esc(markerGlyph(combo.marker))})</span>
            </div>
            <p class="tla-meta"><strong>Correct tone:</strong> ${esc(toneLabel(combo.tone))}</p>
            <button type="button" class="tla-btn tla-primary-btn" data-action="start-test">Start test</button>
          </section>
        `;
      }

      return `
        <section class="tla-card tla-card-learn">
          ${renderCardLead('Step 1: Learn the answer', 'Study pronunciation with audio and meaning before testing.')}
          ${renderWord(combo, { clickable: true, audioUrl: combo.audio_url })}
          <p class="tla-meta"><strong>Pronunciation:</strong> ${esc(combo.pronunciation || '(not set)')}</p>
          ${combo.description ? `<p class="tla-explanation">${esc(combo.description)}</p>` : ''}
          <button type="button" class="tla-btn tla-primary-btn" data-action="start-test">Start test</button>
        </section>
      `;
    };

    const renderFeedbackBlock = () => {
      if (!state.feedback) return '';
      return `
        <div class="tla-feedback ${state.feedback.correct ? 'is-correct' : 'is-incorrect'}">${esc(
          state.feedback.message
        )}</div>
        <button type="button" class="tla-btn tla-primary-btn tla-next-btn" data-action="next-item">Next item</button>
      `;
    };

    const renderSubmitChoice = () => {
      if (state.feedback) return '';
      const disabled = state.selectedChoice ? '' : 'disabled';
      return `<button type="button" class="tla-btn tla-primary-btn tla-submit-btn" data-action="submit-choice" ${disabled}>Next: check answer</button>`;
    };

    const renderChoiceButtons = (options) =>
      options
        .map(
          (option) =>
            `<button type="button" class="tla-btn ${state.selectedChoice === option.value ? 'is-active' : ''}" data-action="pick-choice" data-value="${esc(
              option.value
            )}" data-audio="${esc(option.audio_url || '')}">${esc(option.label)}</button>`
        )
        .join('');

    const renderPronunciationOptions = () => `
      <div class="tla-pron-option-list">
        ${state.round.options
          .map(
            (option) => `
            <div class="tla-pron-option-card">
              <p><strong>${esc(option.pronunciation || '(No pronunciation text)')}</strong></p>
              ${option.description ? `<p>${esc(option.description)}</p>` : ''}
              <button type="button" class="tla-btn ${state.selectedChoice === option.value ? 'is-active' : ''}" data-action="pick-choice" data-value="${esc(
                option.value
              )}" data-audio="${esc(option.audio_url || '')}">Preview / choose</button>
            </div>
          `
          )
          .join('')}
      </div>
    `;

    const renderTestCard = () => {
      if (!state.round) {
        return `
          <section class="tla-card">
            <h3>No training cards available</h3>
            <p>Add testing items in the post editor first.</p>
          </section>
        `;
      }

      const combo = state.round.combo;

      if (state.round.stage === 'vowel') {
        const vowelDisplay = combo.vowel ? combo.vowel.with_aw_ang || combo.vowel.pattern || combo.vowel.label || '' : '';
        return `
          <section class="tla-card tla-card-test">
            ${renderCardLead('Step 2: Test yourself', 'What sound does this vowel make?')}
            <p class="tla-syllable tla-group-neutral"><span class="tla-thai tla-vowel-text">${esc(vowelDisplay)}</span></p>
            <div class="tla-row tla-option-row">${renderChoiceButtons(state.round.options)}</div>
            ${renderSubmitChoice()}
            ${renderFeedbackBlock()}
          </section>
        `;
      }

      if (state.round.stage === 'consonant') {
        return `
          <section class="tla-card tla-card-test">
            ${renderCardLead('Step 2: Test yourself', 'What sound does the consonant make in this word?')}
            ${renderWord(combo)}
            <div class="tla-row tla-option-row">${renderChoiceButtons(state.round.options)}</div>
            ${renderSubmitChoice()}
            ${renderFeedbackBlock()}
          </section>
        `;
      }

      if (state.round.stage === 'tone') {
        const options = state.round.options.map((option) => ({
          ...option,
          label: toneLabel(option.value),
        }));

        return `
          <section class="tla-card tla-card-test">
            ${renderCardLead('Step 2: Test yourself', 'What is the tone of this word?')}
            ${renderWord(combo)}
            <div class="tla-row tla-option-row">${renderChoiceButtons(options)}</div>
            ${renderSubmitChoice()}
            ${renderFeedbackBlock()}
          </section>
        `;
      }

      return `
        <section class="tla-card tla-card-test">
          ${renderCardLead('Step 2: Test yourself', 'Select the correct pronunciation using both audio and description.')}
          ${renderWord(combo)}
          ${renderPronunciationOptions()}
          ${renderSubmitChoice()}
          ${renderFeedbackBlock()}
        </section>
      `;
    };

    const renderMain = () => {
      const mastery = getMasterySnapshot();
      const roundCard =
        mastery.isComplete && !state.keepPracticing
          ? renderCompletionCard()
          : state.phase === 'learn'
          ? renderLearnCard()
          : renderTestCard();

      root.innerHTML = `
        <div class="tla-shell">
          <div class="tla-flip-scene">
            <div class="tla-flip-card ${state.showStats ? 'is-flipped' : ''}">
              <div class="tla-flip-face tla-flip-front">
                <header class="tla-header">
                  <div class="tla-header-row">
                    <div>
                      <h2>${esc(state.config.app_title)}</h2>
                      <p>Learn first, then test. Cards are automatically interleaved in SRS order.</p>
                    </div>
                    <button type="button" class="tla-btn tla-stats-toggle" data-action="toggle-stats">View stats</button>
                  </div>
                </header>
                ${renderProgressBar(mastery)}
                ${renderColorLegend()}
                ${renderStageProgress()}
                ${renderRoundMeta()}
                ${roundCard}
              </div>

              <div class="tla-flip-face tla-flip-back">
                ${renderStatsBack(mastery)}
              </div>
            </div>
          </div>
        </div>
      `;
    };

    const scoreAnswer = async (isCorrect, payload) => {
      const latency = state.timerStarted ? Math.round(performance.now() - state.timerStarted) : 0;

      state.turn += 1;
      applySrsResult(state.round.id, isCorrect);
      applyLocalStats(state.round.stage, isCorrect, latency);
      if (!getMasterySnapshot().isComplete) {
        state.keepPracticing = false;
      }
      state.lastStage = state.round.stage;
      saveSrs();

      state.feedback = {
        correct: isCorrect,
        message: payload.message,
      };

      await recordAttempt({
        itemId: payload.itemId,
        mode: payload.mode,
        correct: isCorrect,
        errorTags: isCorrect ? [] : payload.errorTags,
        latencyMs: latency,
      });

      renderMain();

      if (isCorrect && payload.reinforceAudioUrl) {
        playPreviewAudio(payload.reinforceAudioUrl);
      }
    };

    root.addEventListener('click', async (event) => {
      const target = event.target.closest('[data-action]');
      if (!target) return;

      const action = target.dataset.action;

      if (action === 'toggle-stats') {
        state.showStats = true;
        renderMain();
        return;
      }

      if (action === 'show-game') {
        state.showStats = false;
        renderMain();
        return;
      }

      if (action === 'continue-practice') {
        state.keepPracticing = true;
        prepareRound();
        renderMain();
        return;
      }

      if (state.showStats) {
        return;
      }

      if (action === 'play-audio') {
        playPreviewAudio(target.dataset.audio || '');
        return;
      }

      if (action === 'start-test') {
        if (!state.round) return;
        state.phase = 'test';
        ensureProgress(state.round.id).needsLearn = false;
        state.feedback = null;
        state.selectedChoice = '';
        state.timerStarted = performance.now();
        saveSrs();
        renderMain();
        return;
      }

      if (action === 'next-item') {
        prepareRound();
        renderMain();
        return;
      }

      if (!state.round || state.phase !== 'test' || state.feedback) {
        return;
      }

      if (action === 'pick-choice') {
        state.selectedChoice = target.dataset.value || '';
        playPreviewAudio(target.dataset.audio || '');
        renderMain();
        return;
      }

      if (action !== 'submit-choice') {
        return;
      }

      if (!state.selectedChoice) {
        return;
      }

      const guessed = state.selectedChoice;
      const isCorrect = guessed === state.round.answer;
      const combo = state.round.combo;

      if (state.round.stage === 'vowel') {
        const correctLabel = combo.vowel ? combo.vowel.sound || '(not set)' : 'the configured sound';
        const reinforceAudioUrl =
          state.vowelSoundAudioMap[state.round.answer] || (combo.vowel ? combo.vowel.audio_url : '') || combo.audio_url || '';
        await scoreAnswer(isCorrect, {
          itemId: state.round.id,
          mode: state.round.mode,
          errorTags: ['vowel_mismatch'],
          message: isCorrect ? 'Correct.' : `Not quite. The correct vowel sound is ${correctLabel}.`,
          reinforceAudioUrl,
        });
        return;
      }

      if (state.round.stage === 'consonant') {
        const correctLabel = combo.consonant ? combo.consonant.sound || '(not set)' : 'the configured sound';
        const reinforceAudioUrl = state.consonantSoundAudioMap[state.round.answer] || combo.audio_url || '';
        await scoreAnswer(isCorrect, {
          itemId: state.round.id,
          mode: state.round.mode,
          errorTags: ['consonant_mismatch'],
          message: isCorrect ? 'Correct.' : `Not quite. The correct consonant sound is ${correctLabel}.`,
          reinforceAudioUrl,
        });
        return;
      }

      if (state.round.stage === 'tone') {
        await scoreAnswer(isCorrect, {
          itemId: state.round.id,
          mode: state.round.mode,
          errorTags: ['tone_mismatch'],
          message: isCorrect
            ? `Correct. Tone is ${toneLabel(state.round.answer)}.`
            : `Not quite. The correct tone is ${toneLabel(state.round.answer)}.`,
          reinforceAudioUrl: combo.audio_url || '',
        });
        return;
      }

      await scoreAnswer(isCorrect, {
        itemId: state.round.id,
        mode: state.round.mode,
        errorTags: ['pronunciation_mismatch'],
        message: isCorrect ? 'Correct.' : 'Not quite. Review the pronunciation card and try the next one.',
        reinforceAudioUrl:
          (state.round.options.find((option) => option.value === state.round.answer) || {}).audio_url || combo.audio_url || '',
      });
    });

    const init = async () => {
      root.innerHTML = '<p>Loading Thai literacy app…</p>';

      try {
        state.config = await fetchConfig();
        if (state.config.content.items && state.config.content.items.length) {
          state.combinations = state.config.content.items
            .map((raw, index) => hydrateUnifiedItem(raw, index))
            .filter((combo) => combo.id && combo.text);
        } else {
          const lookups = {
            vowelsById: buildMapById(state.config.content.vowels),
            consonantsById: buildMapById(state.config.content.consonants),
            markersById: buildMapById(state.config.content.tone_markers),
          };

          state.combinations = state.config.content.combinations
            .map((raw) => hydrateCombination(raw, lookups))
            .filter((combo) => combo.id && combo.text);
        }

        state.vowelPool = Object.values(
          buildMapById(state.combinations.map((combo) => combo.vowel).filter((row) => row && row.id))
        );
        state.consonantPool = Object.values(
          buildMapById(state.combinations.map((combo) => combo.consonant).filter((row) => row && row.id))
        );

        state.vowelSoundAudioMap = {};
        state.consonantSoundAudioMap = {};

        state.combinations.forEach((combo) => {
          const vowelSound = String((combo.vowel && combo.vowel.sound) || '').trim();
          if (vowelSound && !state.vowelSoundAudioMap[vowelSound]) {
            state.vowelSoundAudioMap[vowelSound] =
              ((combo.vowel && combo.vowel.audio_url) || combo.audio_url || '').trim();
          }

          const consonantSound = String((combo.consonant && combo.consonant.sound) || '').trim();
          if (consonantSound && !state.consonantSoundAudioMap[consonantSound]) {
            state.consonantSoundAudioMap[consonantSound] = (combo.audio_url || '').trim();
          }
        });

        state.combinationById = buildMapById(state.combinations);
        state.cards = buildCards();

        loadSrs();

        if (!state.cards.length) {
          root.innerHTML =
            '<p>This game has no training items yet. Add testing items in the post editor first.</p>';
          return;
        }

        prepareRound();
        renderMain();
      } catch (_error) {
        root.innerHTML = '<p>Unable to load this game right now.</p>';
      }
    };

    init();
  };

  const findRoots = (scope) => Array.from((scope || document).querySelectorAll('.tla-app-root, #tla-app-root'));

  const mountAll = (scope) => {
    findRoots(scope).forEach((root) => initRoot(root));
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => mountAll(document));
  } else {
    mountAll(document);
  }

  if (typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        mutation.addedNodes.forEach((node) => {
          if (!(node instanceof Element)) return;
          if (node.matches('.tla-app-root, #tla-app-root')) {
            initRoot(node);
          }
          mountAll(node);
        });
      });
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
  }
})();
