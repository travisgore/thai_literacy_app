(() => {
  const editorRoot = document.getElementById('tla_content_editor');
  const jsonField = document.getElementById('tla_content_json');
  const adminConfig = window.TLA_ADMIN && typeof window.TLA_ADMIN === 'object' ? window.TLA_ADMIN : {};

  if (!editorRoot || !jsonField) {
    return;
  }

  const toneOptions = [
    { value: 'mid', label: 'Flat' },
    { value: 'low', label: 'Low' },
    { value: 'falling', label: 'Falling' },
    { value: 'high', label: 'High' },
    { value: 'rising', label: 'Rising' },
    { value: 'unknown', label: 'Unknown' },
  ];

  const consonantClassOptions = [
    { value: 'mid', label: 'Middle class' },
    { value: 'high', label: 'High class' },
    { value: 'low', label: 'Low class' },
  ];

  const toneMarkerIdOptions = [
    { value: 'none', label: 'No tone mark' },
    { value: 'mai_ek', label: 'Mai Ek (่)' },
    { value: 'mai_tho', label: 'Mai Tho (้)' },
    { value: 'mai_tri', label: 'Mai Tri (๊)' },
    { value: 'mai_chattawa', label: 'Mai Chattawa (๋)' },
  ];

  const itemFields = [
    { key: 'id', label: 'Item ID', type: 'text', placeholder: 'item_001' },
    { key: 'word_text', label: 'Thai Word', type: 'text', placeholder: 'กา' },
    { key: 'tone_result', label: 'Correct Tone', type: 'select', options: toneOptions },
    { key: 'pronunciation', label: 'Pronunciation', type: 'text', placeholder: 'gaa' },
    { key: 'description', label: 'Meaning / Description', type: 'textarea', placeholder: 'crow' },
    { key: 'audio_url', label: 'Word Audio', type: 'media_audio', placeholder: 'https://example.com/word-audio.mp3' },
    { key: 'audio_attachment_id', label: 'Word Audio Attachment ID', type: 'text', placeholder: '' },

    { key: 'vowel_character', label: 'Vowel Character', type: 'text', placeholder: 'า / อา' },
    { key: 'vowel_name', label: 'Vowel Name', type: 'text', placeholder: 'Sara Aa' },
    { key: 'vowel_sound', label: 'Vowel Sound', type: 'text', placeholder: 'aa' },
    { key: 'vowel_audio_url', label: 'Vowel Audio', type: 'media_audio', placeholder: 'https://example.com/vowel-audio.mp3' },
    { key: 'vowel_audio_attachment_id', label: 'Vowel Audio Attachment ID', type: 'text', placeholder: '' },
    { key: 'vowel_note', label: 'Vowel Teaching Note', type: 'textarea', placeholder: 'Long aa sound.' },

    { key: 'consonant_character', label: 'Consonant Character', type: 'text', placeholder: 'ก' },
    { key: 'consonant_name', label: 'Consonant Name', type: 'text', placeholder: 'Ko Kai' },
    { key: 'consonant_sound', label: 'Consonant Sound', type: 'text', placeholder: 'k' },
    { key: 'consonant_class', label: 'Consonant Class', type: 'select', options: consonantClassOptions },
    { key: 'consonant_note', label: 'Consonant Teaching Note', type: 'textarea', placeholder: 'Middle-class consonant.' },

    { key: 'tone_marker_id', label: 'Tone Marker ID', type: 'select', options: toneMarkerIdOptions },
    { key: 'tone_marker_name', label: 'Tone Marker Name', type: 'text', placeholder: 'Mai Ek' },
    { key: 'tone_marker_character', label: 'Tone Marker Character', type: 'text', placeholder: '่' },
    { key: 'tone_marker_note', label: 'Tone Marker Teaching Note', type: 'textarea', placeholder: 'Lowers tone in many words.' },
  ];

  const fieldGroups = [
    {
      title: 'Word & Audio',
      keys: ['word_text', 'tone_result', 'pronunciation', 'description', 'audio_url'],
    },
    {
      title: 'Vowel',
      keys: ['vowel_character', 'vowel_name', 'vowel_sound', 'vowel_audio_url', 'vowel_note'],
    },
    {
      title: 'Consonant',
      keys: ['consonant_character', 'consonant_name', 'consonant_sound', 'consonant_class', 'consonant_note'],
    },
    {
      title: 'Tone Marker',
      keys: ['tone_marker_id', 'tone_marker_name', 'tone_marker_character', 'tone_marker_note'],
    },
  ];

  const legacyContentKeys = new Set([
    'items',
    'vowels',
    'consonants',
    'tone_markers',
    'combinations',
    'graphemes',
    'syllables',
    'vowel_families',
    'tone_rules',
    'games',
  ]);

  const fieldMap = itemFields.reduce((acc, field) => {
    acc[field.key] = field;
    return acc;
  }, {});

  const escapeHtml = (value) =>
    String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');

  const parseContent = () => {
    try {
      const parsed = JSON.parse(jsonField.value || '{}');
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        return { value: parsed, valid: true };
      }
      return { value: null, valid: false };
    } catch (_error) {
      return { value: null, valid: false };
    }
  };

  const toneMarkerLabelMap = {
    none: 'No tone mark',
    mai_ek: 'Mai Ek',
    mai_tho: 'Mai Tho',
    mai_tri: 'Mai Tri',
    mai_chattawa: 'Mai Chattawa',
  };

  const toneMarkerCharMap = {
    none: '',
    mai_ek: '่',
    mai_tho: '้',
    mai_tri: '๊',
    mai_chattawa: '๋',
  };

  const createEmptyItem = () => {
    const item = {};
    itemFields.forEach((field) => {
      item[field.key] = '';
    });
    item.id = `item_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;
    item.tone_marker_id = 'none';
    item.tone_marker_name = 'No tone mark';
    item.tone_marker_character = '';
    return item;
  };

  const makeIdBase = (item) => {
    const base = String(item.word_text || item.pronunciation || item.description || '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');
    return base || 'item';
  };

  const ensureUniqueItemIds = (items) => {
    const seen = new Set();
    return items.map((item, index) => {
      const next = { ...item };
      const desired = String(next.id || '').trim();
      const base = desired || `${makeIdBase(next)}_${index + 1}`;
      let candidate = base;
      let suffix = 2;
      while (!candidate || seen.has(candidate)) {
        candidate = `${base}_${suffix}`;
        suffix += 1;
      }
      next.id = candidate;
      seen.add(candidate);
      return next;
    });
  };

  const normalizeItem = (item) => {
    const source = item && typeof item === 'object' ? { ...item } : {};
    const normalized = createEmptyItem();

    itemFields.forEach((field) => {
      if (typeof source[field.key] === 'string') {
        normalized[field.key] = source[field.key];
      } else if (source[field.key] !== undefined && source[field.key] !== null) {
        normalized[field.key] = String(source[field.key]);
      }
    });

    if (!normalized.tone_marker_name && normalized.tone_marker_id) {
      normalized.tone_marker_name = toneMarkerLabelMap[normalized.tone_marker_id] || normalized.tone_marker_id;
    }

    if (!normalized.tone_marker_character && normalized.tone_marker_id) {
      normalized.tone_marker_character = toneMarkerCharMap[normalized.tone_marker_id] || '';
    }

    return normalized;
  };

  const migrateToItems = (rawContent) => {
    const source = rawContent && typeof rawContent === 'object' ? rawContent : {};

    if (Array.isArray(source.items) && source.items.length) {
      return source.items.map((item) => normalizeItem(item));
    }

    const vowelsById = {};
    (Array.isArray(source.vowels) ? source.vowels : []).forEach((vowel) => {
      if (!vowel || !vowel.id) return;
      vowelsById[vowel.id] = {
        character: String(vowel.with_aw_ang || vowel.pattern || ''),
        name: String(vowel.label || vowel.id || ''),
        sound: String(vowel.sound || ''),
        audio_url: String(vowel.audio_url || ''),
        note: String(vowel.hint || ''),
      };
    });

    const consonantsById = {};
    (Array.isArray(source.consonants) ? source.consonants : []).forEach((consonant) => {
      if (!consonant || !consonant.id) return;
      consonantsById[consonant.id] = {
        character: String(consonant.glyph || ''),
        name: String(consonant.name || consonant.id || ''),
        sound: String(consonant.sound || ''),
        class: String(consonant.class || ''),
        note: String(consonant.hint || ''),
      };
    });

    const markersById = {};
    (Array.isArray(source.tone_markers) ? source.tone_markers : []).forEach((marker) => {
      if (!marker || !marker.id) return;
      markersById[marker.id] = {
        name: String(marker.label || marker.id || ''),
        character: String(marker.glyph || ''),
        note: String(marker.hint || ''),
      };
    });

    const combos = Array.isArray(source.combinations)
      ? source.combinations
      : Array.isArray(source.syllables)
      ? source.syllables
      : [];

    return combos.map((combo, index) => {
      const consonant = consonantsById[combo.consonant_id] || {};
      const vowel = vowelsById[combo.vowel_id] || {};
      const markerId = String(combo.tone_marker_id || combo.tone_mark || 'none');
      const marker = markersById[markerId] || {};

      return normalizeItem({
        id: combo.id || `item_${index + 1}`,
        word_text: combo.text || '',
        tone_result: combo.tone || combo.resulting_tone || '',
        pronunciation: combo.pronunciation || combo.ipa || '',
        description: combo.description || combo.meaning || '',
        audio_url: combo.audio_url || '',

        vowel_character: vowel.character || '',
        vowel_name: vowel.name || '',
        vowel_sound: vowel.sound || '',
        vowel_audio_url: vowel.audio_url || combo.vowel_audio_url || '',
        vowel_note: vowel.note || '',

        consonant_character: consonant.character || '',
        consonant_name: consonant.name || '',
        consonant_sound: consonant.sound || '',
        consonant_class: consonant.class || combo.consonant_class || combo.initial_class || '',
        consonant_note: consonant.note || '',

        tone_marker_id: markerId,
        tone_marker_name: marker.name || toneMarkerLabelMap[markerId] || '',
        tone_marker_character: marker.character || toneMarkerCharMap[markerId] || '',
        tone_marker_note: marker.note || '',
      });
    });
  };

  const parsedContent = parseContent();
  if (!parsedContent.valid) {
    editorRoot.innerHTML =
      '<p class="description">Content editor could not load because stored JSON is invalid. Fix JSON below, then save and reload.</p>';
    jsonField.classList.remove('tla-content-json-field');
    return;
  }

  const rawContent = parsedContent.value;
  const extraContent = {};

  Object.keys(rawContent).forEach((key) => {
    if (!legacyContentKeys.has(key)) {
      extraContent[key] = rawContent[key];
    }
  });

  let state = {
    items: ensureUniqueItemIds(migrateToItems(rawContent)),
    searchTerm: '',
    status: '',
    busy: false,
  };

  const audioFieldConfig = {
    audio_url: {
      attachmentKey: 'audio_attachment_id',
      sourceKeys: ['word_text', 'pronunciation', 'description'],
      label: 'word',
    },
    vowel_audio_url: {
      attachmentKey: 'vowel_audio_attachment_id',
      sourceKeys: ['vowel_character', 'vowel_sound', 'vowel_name'],
      label: 'vowel',
    },
  };

  const audioFieldKeys = Object.keys(audioFieldConfig);

  const syncHiddenJson = () => {
    state.items = ensureUniqueItemIds(state.items);
    jsonField.value = JSON.stringify({
      ...extraContent,
      items: state.items,
    });
  };

  const buildOptions = (field, value) => {
    const options = [...(field.options || [])];
    if (value && !options.some((option) => option.value === value)) {
      options.push({ value, label: `${value} (custom)` });
    }

    return options
      .map((option) => {
        const selected = option.value === value ? ' selected' : '';
        return `<option value="${escapeHtml(option.value)}"${selected}>${escapeHtml(option.label)}</option>`;
      })
      .join('');
  };

  const renderField = (rowIndex, row, fieldKey) => {
    const field = fieldMap[fieldKey];
    if (!field) return '';

    const inputId = `tla_item_${rowIndex}_${field.key}`;
    const value = row[field.key] || '';
    const baseAttrs = [
      `id="${inputId}"`,
      'data-action="update-field"',
      `data-index="${rowIndex}"`,
      `data-field-key="${field.key}"`,
    ].join(' ');

    let controlHtml = '';
    if (field.type === 'textarea') {
      controlHtml = `<textarea ${baseAttrs} rows="3" class="tla-editor-control">${escapeHtml(value)}</textarea>`;
    } else if (field.type === 'select') {
      controlHtml = `<select ${baseAttrs} class="tla-editor-control">${buildOptions(field, value)}</select>`;
    } else if (field.type === 'media_audio') {
      const config = audioFieldConfig[field.key];
      const attachmentId = config ? String(row[config.attachmentKey] || '') : '';
      const buttonDisabled = state.busy ? ' disabled' : '';
      const placeholder = field.placeholder ? ` placeholder="${escapeHtml(field.placeholder)}"` : '';
      controlHtml = `
        <div class="tla-editor-media">
          <input ${baseAttrs} type="text" value="${escapeHtml(value)}"${placeholder} class="tla-editor-control tla-editor-media-url" />
          <div class="tla-editor-media-actions">
            <button type="button" class="button button-secondary" data-action="generate-audio" data-index="${rowIndex}" data-field-key="${field.key}"${buttonDisabled}>Generate Audio (Random Male/Female)</button>
            <button type="button" class="button button-secondary" data-action="select-audio" data-index="${rowIndex}" data-field-key="${field.key}"${buttonDisabled}>Upload / Select Audio</button>
            <button type="button" class="button-link-delete" data-action="clear-audio" data-index="${rowIndex}" data-field-key="${field.key}" data-attachment-id="${escapeHtml(attachmentId)}"${buttonDisabled}>Clear</button>
          </div>
          ${
            value
              ? `<audio class="tla-editor-audio-preview" controls preload="none" src="${escapeHtml(value)}"></audio>`
              : '<p class="description">No audio selected.</p>'
          }
        </div>
      `;
    } else {
      const placeholder = field.placeholder ? ` placeholder="${escapeHtml(field.placeholder)}"` : '';
      controlHtml = `<input ${baseAttrs} type="text" value="${escapeHtml(value)}"${placeholder} class="tla-editor-control" />`;
    }

    const widthClass = field.type === 'textarea' || field.type === 'media_audio' ? ' is-wide' : '';

    return `
      <label class="tla-editor-field${widthClass}" for="${inputId}">
        <span class="tla-editor-label">${escapeHtml(field.label)}</span>
        ${controlHtml}
      </label>
    `;
  };

  const itemMatchesSearch = (item, searchTerm) => {
    const query = searchTerm.trim().toLowerCase();
    if (!query) return true;

    return Object.values(item).some((value) => String(value || '').toLowerCase().includes(query));
  };

  const displayOrDash = (value) => {
    const text = String(value || '').trim();
    return text || '—';
  };

  const consonantSummaryClass = (consonantClass) => {
    if (consonantClass === 'high') return 'tla-summary-consonant-high';
    if (consonantClass === 'mid') return 'tla-summary-consonant-mid';
    if (consonantClass === 'low') return 'tla-summary-consonant-low';
    return 'tla-summary-consonant-neutral';
  };

  const summaryValues = (item) => ({
    vowel: displayOrDash(item.vowel_character || item.vowel_name),
    consonant: displayOrDash(item.consonant_character || item.consonant_name),
    word: displayOrDash(item.word_text),
    consonantClass: consonantSummaryClass(item.consonant_class),
  });

  const renderRows = () => {
    const visibleIndexes = state.items
      .map((item, index) => ({ item, index }))
      .filter(({ item }) => itemMatchesSearch(item, state.searchTerm));

    if (!visibleIndexes.length) {
      return '<div class="tla-editor-empty">No matching items. Try a different search or add a new item.</div>';
    }

    return visibleIndexes
      .map(({ item, index }) => {
        const summary = summaryValues(item);

        return `
          <article class="tla-editor-card">
            <header class="tla-editor-card-head">
              <h4 class="tla-editor-summary">
                <span class="tla-summary-part tla-summary-vowel" data-summary-index="${index}" data-summary-type="vowel">Vowel: ${escapeHtml(summary.vowel)}</span>
                <span class="tla-summary-part tla-summary-consonant ${summary.consonantClass}" data-summary-index="${index}" data-summary-type="consonant">Consonant: ${escapeHtml(summary.consonant)}</span>
                <span class="tla-summary-part tla-summary-word" data-summary-index="${index}" data-summary-type="word">Word: ${escapeHtml(summary.word)}</span>
              </h4>
              <button type="button" class="button-link-delete" data-action="remove-item" data-index="${index}">Remove</button>
            </header>

            ${fieldGroups
              .map(
                (group) => `
                  <section class="tla-editor-group">
                    <h5>${escapeHtml(group.title)}</h5>
                    <div class="tla-editor-grid">
                      ${group.keys.map((fieldKey) => renderField(index, item, fieldKey)).join('')}
                    </div>
                  </section>
                `
              )
              .join('')}
          </article>
        `;
      })
      .join('');
  };

  const render = () => {
    const visibleCount = state.items.filter((item) => itemMatchesSearch(item, state.searchTerm)).length;

    editorRoot.innerHTML = `
      <div class="tla-editor-shell">
        <section class="tla-editor-panel">
          <header class="tla-editor-panel-head">
            <div>
              <h3>Testing Items</h3>
              <p>Keep all fields for each testing item in one card: vowel, consonant, tone marker, pronunciation, and audio.</p>
            </div>
            <div class="tla-editor-actions">
              <input
                type="search"
                class="tla-editor-search"
                placeholder="Search any field..."
                value="${escapeHtml(state.searchTerm)}"
                data-action="search-items"
              />
              <button type="button" class="button" data-action="generate-missing-audio"${state.busy ? ' disabled' : ''}>Generate missing audio</button>
              <button type="button" class="button button-link-delete" data-action="clear-all-audio"${state.busy ? ' disabled' : ''}>Remove all audio</button>
              <button type="button" class="button button-primary" data-action="add-item">Add testing item</button>
            </div>
          </header>
          ${state.status ? `<p class="description tla-editor-status">${escapeHtml(state.status)}</p>` : ''}
          <p class="description">Showing ${visibleCount} of ${state.items.length} items.</p>
          <div class="tla-editor-list">${renderRows()}</div>
        </section>
      </div>
    `;
  };

  const parseIndex = (value) => {
    const index = Number.parseInt(value, 10);
    return Number.isNaN(index) ? -1 : index;
  };

  const updateCardSummary = (rowIndex) => {
    const item = state.items[rowIndex];
    if (!item) return;

    const summary = summaryValues(item);

    const vowelNodes = editorRoot.querySelectorAll(
      `[data-summary-index="${rowIndex}"][data-summary-type="vowel"]`
    );
    vowelNodes.forEach((node) => {
      node.textContent = `Vowel: ${summary.vowel}`;
    });

    const consonantNodes = editorRoot.querySelectorAll(
      `[data-summary-index="${rowIndex}"][data-summary-type="consonant"]`
    );
    consonantNodes.forEach((node) => {
      node.textContent = `Consonant: ${summary.consonant}`;
      node.classList.remove(
        'tla-summary-consonant-high',
        'tla-summary-consonant-mid',
        'tla-summary-consonant-low',
        'tla-summary-consonant-neutral'
      );
      node.classList.add(summary.consonantClass);
    });

    const wordNodes = editorRoot.querySelectorAll(
      `[data-summary-index="${rowIndex}"][data-summary-type="word"]`
    );
    wordNodes.forEach((node) => {
      node.textContent = `Word: ${summary.word}`;
    });
  };

  const setFieldValue = (rowIndex, fieldKey, value) => {
    if (rowIndex < 0 || rowIndex >= state.items.length || !fieldMap[fieldKey]) {
      return false;
    }

    const current = state.items[rowIndex];
    const next = {
      ...current,
      [fieldKey]: value,
    };

    if (fieldKey === 'tone_marker_id') {
      next.tone_marker_name = toneMarkerLabelMap[value] || next.tone_marker_name || '';
      next.tone_marker_character = toneMarkerCharMap[value] || '';
    }

    state.items[rowIndex] = next;
    syncHiddenJson();
    updateCardSummary(rowIndex);
    return true;
  };

  const setBusy = (isBusy, statusMessage = '') => {
    state.busy = !!isBusy;
    state.status = statusMessage;
    render();
  };

  const getAudioSourceText = (item, fieldKey) => {
    const config = audioFieldConfig[fieldKey];
    if (!config) return '';
    const found = config.sourceKeys
      .map((key) => String(item[key] || '').trim())
      .find((value) => value.length > 0);
    return found || '';
  };

  const postAjax = async (action, payload = {}) => {
    if (!adminConfig.ajaxUrl || !adminConfig.nonce) {
      throw new Error('Admin audio actions are not configured.');
    }

    const body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', adminConfig.nonce);
    Object.keys(payload).forEach((key) => {
      const value = payload[key];
      if (value !== undefined && value !== null) {
        body.set(key, String(value));
      }
    });

    const response = await window.fetch(adminConfig.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      body: body.toString(),
    });

    const data = await response.json();
    if (!response.ok || !data || data.success !== true) {
      const errorMessage =
        data && data.data && typeof data.data.message === 'string'
          ? data.data.message
          : `Request failed (${response.status}).`;
      throw new Error(errorMessage);
    }

    return data.data || {};
  };

  const generateAudioForField = async (rowIndex, fieldKey) => {
    const item = state.items[rowIndex];
    const config = audioFieldConfig[fieldKey];
    if (!item || !config) return false;

    const sourceText = getAudioSourceText(item, fieldKey);
    if (!sourceText) {
      window.alert(`Cannot generate ${config.label} audio because there is no source text yet.`);
      return false;
    }

    setBusy(true, `Generating ${config.label} audio...`);
    try {
      const result = await postAjax('tla_generate_tts_audio', {
        post_id: adminConfig.postId || 0,
        field_key: fieldKey,
        text: sourceText,
        existing_attachment_id: String(item[config.attachmentKey] || ''),
      });

      setFieldValue(rowIndex, fieldKey, result.url || '');
      setFieldValue(rowIndex, config.attachmentKey, String(result.attachment_id || ''));
      state.status = `Generated ${config.label} audio (${result.voice_gender || 'random'} voice).`;
      render();
      return true;
    } catch (error) {
      window.alert(error.message || 'Could not generate audio.');
      setBusy(false, '');
      return false;
    } finally {
      state.busy = false;
      render();
    }
  };

  const clearAudioForField = async (rowIndex, fieldKey) => {
    const item = state.items[rowIndex];
    const config = audioFieldConfig[fieldKey];
    if (!item || !config) return false;

    const audioUrl = String(item[fieldKey] || '').trim();
    const attachmentId = String(item[config.attachmentKey] || '').trim();
    if (!audioUrl && !attachmentId) {
      return true;
    }

    setBusy(true, 'Removing audio from Media Library...');
    try {
      await postAjax('tla_delete_audio_attachment', {
        post_id: adminConfig.postId || 0,
        attachment_id: attachmentId,
        audio_url: audioUrl,
      });
      setFieldValue(rowIndex, fieldKey, '');
      setFieldValue(rowIndex, config.attachmentKey, '');
      state.status = 'Audio removed.';
      render();
      return true;
    } catch (error) {
      window.alert(error.message || 'Could not remove audio.');
      setBusy(false, '');
      return false;
    } finally {
      state.busy = false;
      render();
    }
  };

  const generateMissingAudio = async () => {
    const pending = [];
    state.items.forEach((item, rowIndex) => {
      audioFieldKeys.forEach((fieldKey) => {
        const existingUrl = String(item[fieldKey] || '').trim();
        const sourceText = getAudioSourceText(item, fieldKey);
        if (!existingUrl && sourceText) {
          pending.push({ rowIndex, fieldKey });
        }
      });
    });

    if (!pending.length) {
      state.status = 'No missing audio found.';
      render();
      return;
    }

    setBusy(true, `Generating missing audio (0/${pending.length})...`);
    let successCount = 0;

    for (let i = 0; i < pending.length; i += 1) {
      const job = pending[i];
      const item = state.items[job.rowIndex];
      const config = audioFieldConfig[job.fieldKey];
      const sourceText = getAudioSourceText(item, job.fieldKey);
      state.status = `Generating missing audio (${i + 1}/${pending.length})...`;
      render();

      try {
        const result = await postAjax('tla_generate_tts_audio', {
          post_id: adminConfig.postId || 0,
          field_key: job.fieldKey,
          text: sourceText,
          existing_attachment_id: String(item[config.attachmentKey] || ''),
        });
        setFieldValue(job.rowIndex, job.fieldKey, result.url || '');
        setFieldValue(job.rowIndex, config.attachmentKey, String(result.attachment_id || ''));
        successCount += 1;
      } catch (_error) {
        // Continue so one bad item does not stop the full batch.
      }
    }

    state.busy = false;
    state.status = `Generated ${successCount} audio file${successCount === 1 ? '' : 's'} out of ${pending.length} missing item${pending.length === 1 ? '' : 's'}.`;
    render();
  };

  const clearAllAudio = async () => {
    const targets = [];
    state.items.forEach((item, rowIndex) => {
      audioFieldKeys.forEach((fieldKey) => {
        const config = audioFieldConfig[fieldKey];
        const audioUrl = String(item[fieldKey] || '').trim();
        const attachmentId = String(item[config.attachmentKey] || '').trim();
        if (audioUrl || attachmentId) {
          targets.push({ rowIndex, fieldKey, attachmentId, audioUrl });
        }
      });
    });

    if (!targets.length) {
      state.status = 'No audio to remove.';
      render();
      return;
    }

    const confirmed = window.confirm(
      `This will remove ${targets.length} audio file${targets.length === 1 ? '' : 's'} and delete them from Media Library. Continue?`
    );
    if (!confirmed) {
      return;
    }

    setBusy(true, `Removing audio (0/${targets.length})...`);
    let removedCount = 0;

    for (let i = 0; i < targets.length; i += 1) {
      const target = targets[i];
      const config = audioFieldConfig[target.fieldKey];
      state.status = `Removing audio (${i + 1}/${targets.length})...`;
      render();

      try {
        await postAjax('tla_delete_audio_attachment', {
          post_id: adminConfig.postId || 0,
          attachment_id: target.attachmentId,
          audio_url: target.audioUrl,
        });
        setFieldValue(target.rowIndex, target.fieldKey, '');
        setFieldValue(target.rowIndex, config.attachmentKey, '');
        removedCount += 1;
      } catch (_error) {
        // Continue so one failure does not stop the full cleanup.
      }
    }

    state.busy = false;
    state.status = `Removed ${removedCount} audio file${removedCount === 1 ? '' : 's'} out of ${targets.length}.`;
    render();
  };

  const openAudioPicker = (rowIndex, fieldKey) => {
    if (typeof wp === 'undefined' || !wp.media) {
      window.alert('WordPress media uploader is not available on this screen.');
      return;
    }

    const frame = wp.media({
      title: 'Select Audio File',
      button: { text: 'Use This Audio' },
      library: { type: ['audio'] },
      multiple: false,
    });

    frame.on('select', () => {
      const attachment = frame.state().get('selection').first();
      if (!attachment) {
        return;
      }

      const data = attachment.toJSON();
      const audioUrl = data && typeof data.url === 'string' ? data.url : '';
      const config = audioFieldConfig[fieldKey];
      if (setFieldValue(rowIndex, fieldKey, audioUrl)) {
        if (config) {
          setFieldValue(rowIndex, config.attachmentKey, String(data && data.id ? data.id : ''));
        }
        render();
      }
    });

    frame.open();
  };

  editorRoot.addEventListener('input', (event) => {
    const target = event.target;

    if (target.matches('[data-action="search-items"]')) {
      state.searchTerm = target.value || '';
      render();
      return;
    }

    if (target.matches('[data-action="update-field"]')) {
      const index = parseIndex(target.dataset.index);
      const fieldKey = target.dataset.fieldKey;
      setFieldValue(index, fieldKey, target.value);
    }
  });

  editorRoot.addEventListener('change', (event) => {
    const target = event.target;
    if (target.matches('[data-action="update-field"]')) {
      const index = parseIndex(target.dataset.index);
      const fieldKey = target.dataset.fieldKey;
      setFieldValue(index, fieldKey, target.value);
    }
  });

  editorRoot.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-action]');
    if (!button) return;

    const action = button.dataset.action;
    const rowIndex = parseIndex(button.dataset.index);
    const fieldKey = button.dataset.fieldKey;

    if (action === 'add-item') {
      state.items.unshift(createEmptyItem());
      state.searchTerm = '';
      syncHiddenJson();
      render();
      return;
    }

    if (action === 'generate-missing-audio') {
      if (!state.busy) {
        generateMissingAudio();
      }
      return;
    }

    if (action === 'clear-all-audio') {
      if (!state.busy) {
        clearAllAudio();
      }
      return;
    }

    if (action === 'remove-item') {
      if (rowIndex < 0 || rowIndex >= state.items.length) return;
      state.items.splice(rowIndex, 1);
      syncHiddenJson();
      render();
      return;
    }

    if (action === 'generate-audio') {
      if (!state.busy) {
        generateAudioForField(rowIndex, fieldKey);
      }
      return;
    }

    if (action === 'select-audio') {
      if (state.busy) return;
      openAudioPicker(rowIndex, fieldKey);
      return;
    }

    if (action === 'clear-audio') {
      if (!state.busy) {
        clearAudioForField(rowIndex, fieldKey);
      }
    }
  });

  syncHiddenJson();
  render();
})();

(() => {
  const copyToClipboard = async (value) => {
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(value);
      return;
    }

    const tempInput = document.createElement('textarea');
    tempInput.value = value;
    tempInput.setAttribute('readonly', 'readonly');
    tempInput.style.position = 'absolute';
    tempInput.style.left = '-9999px';
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
  };

  document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action="copy-shortcode"]');
    if (!button) {
      return;
    }

    const targetId = button.dataset.copyTarget;
    const feedbackId = button.dataset.feedbackTarget;
    const source = targetId ? document.getElementById(targetId) : null;
    const feedback = feedbackId ? document.getElementById(feedbackId) : null;

    if (!source) {
      return;
    }

    source.focus();
    source.select();

    try {
      await copyToClipboard(source.value);
      if (feedback) {
        feedback.textContent = 'Shortcode copied.';
      }
    } catch (_error) {
      if (feedback) {
        feedback.textContent = 'Unable to copy automatically. Copy the shortcode manually.';
      }
    }
  });
})();
