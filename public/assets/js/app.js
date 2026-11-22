(() => {
  'use strict';
  const privacyNotice = document.querySelector('[data-lgpd-notice]');
  try {
    if (sessionStorage.getItem('ruffino-privacy-dismissed') === '1' && privacyNotice) privacyNotice.hidden = true;
  } catch (_) { /* O botão funciona mesmo com armazenamento indisponível. */ }
  document.querySelector('[data-lgpd-close]')?.addEventListener('click', () => {
    if (privacyNotice) privacyNotice.hidden = true;
    try { sessionStorage.setItem('ruffino-privacy-dismissed', '1'); } catch (_) {}
  });
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const navToggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.main-nav');
  navToggle?.addEventListener('click', () => {
    const open = nav?.classList.toggle('open') ?? false;
    navToggle.setAttribute('aria-expanded', String(open));
  });

  document.querySelectorAll('[data-carousel]').forEach((carousel) => {
    const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
    const current = carousel.querySelector('[data-carousel-current]');
    let index = 0;
    const show = (next) => {
      if (!slides.length) return;
      index = (next + slides.length) % slides.length;
      slides.forEach((slide, slideIndex) => {
        slide.hidden = slideIndex !== index;
      });
      if (current) current.textContent = String(index + 1);
    };
    carousel.querySelector('[data-carousel-prev]')?.addEventListener('click', () => show(index - 1));
    carousel.querySelector('[data-carousel-next]')?.addEventListener('click', () => show(index + 1));
    carousel.addEventListener('keydown', (event) => {
      if (!event.target.closest('[data-carousel-slide]') || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
      event.preventDefault();
      show(index + (event.key === 'ArrowRight' ? 1 : -1));
      slides[index]?.focus();
    });
    show(0);
  });

  const imageLightbox = document.querySelector('[data-image-lightbox]');
  if (imageLightbox) {
    const lightboxImage = imageLightbox.querySelector('[data-lightbox-image]');
    const lightboxCaption = imageLightbox.querySelector('[data-lightbox-caption]');
    const previousButton = imageLightbox.querySelector('[data-lightbox-prev]');
    const nextButton = imageLightbox.querySelector('[data-lightbox-next]');
    let lightboxItems = [];
    let lightboxIndex = 0;
    let lastTrigger = null;
    const renderLightbox = (nextIndex) => {
      if (!lightboxItems.length) return;
      lightboxIndex = (nextIndex + lightboxItems.length) % lightboxItems.length;
      const image = lightboxItems[lightboxIndex];
      lightboxImage.src = image.currentSrc || image.src;
      lightboxImage.alt = image.alt;
      lightboxCaption.textContent = lightboxItems.length > 1
        ? `Foto ${lightboxIndex + 1} de ${lightboxItems.length} — ${image.alt}`
        : image.alt;
      previousButton.hidden = nextButton.hidden = lightboxItems.length < 2;
    };
    const openLightbox = (trigger) => {
      const carousel = trigger.closest('[data-carousel]');
      const slide = trigger.closest('[data-carousel-slide]');
      lightboxItems = [...(carousel?.querySelectorAll('[data-carousel-slide] img') || [])];
      const activeImage = slide?.querySelector('img');
      lightboxIndex = Math.max(0, lightboxItems.indexOf(activeImage));
      if (!activeImage || !lightboxItems.length) return;
      lastTrigger = trigger;
      renderLightbox(lightboxIndex);
      imageLightbox.showModal();
      imageLightbox.querySelector('.image-lightbox-close button')?.focus();
    };
    document.querySelectorAll('[data-image-zoom]').forEach((trigger) => {
      trigger.addEventListener('click', () => openLightbox(trigger));
      trigger.addEventListener('keydown', (event) => {
        if (!['Enter', ' '].includes(event.key)) return;
        event.preventDefault();
        openLightbox(trigger);
      });
    });
    previousButton?.addEventListener('click', () => renderLightbox(lightboxIndex - 1));
    nextButton?.addEventListener('click', () => renderLightbox(lightboxIndex + 1));
    imageLightbox.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      if (event.key === 'Home') renderLightbox(0);
      else if (event.key === 'End') renderLightbox(lightboxItems.length - 1);
      else renderLightbox(lightboxIndex + (event.key === 'ArrowRight' ? 1 : -1));
    });
    imageLightbox.addEventListener('click', (event) => {
      if (event.target === imageLightbox) imageLightbox.close();
    });
    imageLightbox.addEventListener('close', () => {
      lightboxImage.removeAttribute('src');
      lastTrigger?.focus();
    });
  }

  document.querySelectorAll('[data-gallery-sortable]').forEach((grid) => {
    let dragged = null;
    let originalOrder = [];
    const status = grid.parentElement?.querySelector('[data-gallery-order-status]');
    const items = () => [...grid.querySelectorAll('[data-gallery-image]')];
    const ids = () => items().map((item) => item.dataset.imageId);
    const refreshLabels = () => {
      const ordered = items();
      ordered.forEach((item, index) => {
        const label = item.querySelector('[data-photo-number]');
        if (label) label.textContent = `Foto ${index + 1}`;
        item.querySelectorAll('input[name="direction"]').forEach((input) => {
          const button = input.closest('form')?.querySelector('button');
          if (button) button.disabled = input.value === 'up' ? index === 0 : index === ordered.length - 1;
        });
      });
    };
    const restore = (order) => {
      const byId = new Map(items().map((item) => [item.dataset.imageId, item]));
      order.forEach((id) => { if (byId.has(id)) grid.append(byId.get(id)); });
      refreshLabels();
    };
    const save = async (previousOrder) => {
      const body = new URLSearchParams({_csrf: grid.dataset.csrf || '', finalist_id: grid.dataset.finalistId || ''});
      ids().forEach((id) => body.append('image_ids[]', id));
      grid.classList.add('is-saving');
      if (status) status.textContent = 'Salvando nova ordem…';
      try {
        const response = await fetch(grid.dataset.reorderUrl, {method: 'POST', credentials: 'same-origin', headers: {Accept: 'application/json'}, body});
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || 'Não foi possível salvar a ordem.');
        refreshLabels();
        if (status) status.textContent = 'Ordem salva.';
      } catch (error) {
        restore(previousOrder);
        if (status) status.textContent = error.message || 'Não foi possível salvar a ordem.';
      } finally {
        grid.classList.remove('is-saving');
      }
    };
    grid.addEventListener('dragstart', (event) => {
      const item = event.target.closest('[data-gallery-image]');
      if (!item || item.classList.contains('is-cover') || grid.classList.contains('is-saving')) {
        event.preventDefault();
        return;
      }
      dragged = item;
      originalOrder = ids();
      item.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', item.dataset.imageId || '');
    });
    grid.addEventListener('dragover', (event) => {
      if (!dragged) return;
      event.preventDefault();
      event.dataTransfer.dropEffect = 'move';
      const target = event.target.closest('[data-gallery-image]');
      if (!target || target === dragged) return;
      if (target.classList.contains('is-cover')) {
        target.after(dragged);
        return;
      }
      const rect = target.getBoundingClientRect();
      const after = event.clientY > rect.top + rect.height / 2;
      grid.insertBefore(dragged, after ? target.nextSibling : target);
    });
    grid.addEventListener('drop', (event) => event.preventDefault());
    grid.addEventListener('dragend', () => {
      if (!dragged) return;
      dragged.classList.remove('is-dragging');
      const changed = originalOrder.join(',') !== ids().join(',');
      const previousOrder = originalOrder;
      dragged = null;
      originalOrder = [];
      if (changed) save(previousOrder);
    });
  });

  document.querySelectorAll('.gallery-upload-form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      const input = form.querySelector('input[type="file"]');
      const files = [...(input?.files || [])];
      const maxFileBytes = Number(form.dataset.maxFileBytes) || 32 * 1024 * 1024;
      const maxFileMb = Math.ceil(maxFileBytes / 1024 / 1024);
      const oversized = files.find((file) => file.size > maxFileBytes);
      if (oversized) {
        event.preventDefault();
        window.alert(`A foto “${oversized.name}” ultrapassa o limite de ${maxFileMb} MB.`);
        return;
      }
      const total = files.reduce((sum, file) => sum + file.size, 0);
      if (total > 88 * 1024 * 1024) {
        event.preventDefault();
        window.alert('O lote ultrapassa o limite de envio. Selecione menos fotos e envie em duas etapas.');
        return;
      }
      const button = form.querySelector('button[type="submit"], button:not([type])');
      if (button) {
        button.textContent = 'Enviando fotos…';
      }
    });
  });

  const analyticsView = document.body.dataset.analyticsView;
  if (analyticsView) {
    let pending = false;
    const ping = async () => {
      if (document.visibilityState !== 'visible' || pending) return;
      pending = true;
      try {
        await fetch('/api/analytics/presence', {method: 'POST', credentials: 'same-origin', body: new URLSearchParams({_csrf: csrfToken, view: analyticsView})});
      } catch (_) { /* A navegação não depende do analytics. */ }
      finally { pending = false; }
    };
    ping();
    setInterval(ping, 30000);
    document.addEventListener('visibilitychange', ping);
  }
  if (document.querySelector('[data-online-count]')) {
    let pending = false;
    const refreshOnline = async () => {
      if (document.visibilityState !== 'visible' || pending) return;
      pending = true;
      try {
        const response = await fetch('/admin/analytics/online', {credentials: 'same-origin', cache: 'no-store'});
        if (!response.ok) throw new Error('indisponível');
        const data = await response.json();
        if (!data.ok) throw new Error('indisponível');
        document.querySelectorAll('[data-online-count]').forEach(el => { el.textContent = data.online; });
        document.querySelectorAll('[data-online-status]').forEach(el => { el.textContent = 'Atualizado às ' + data.updated_at; });
      } catch (_) {
        document.querySelectorAll('[data-online-status]').forEach(el => { el.textContent = 'Sem atualização ao vivo; tentando novamente…'; });
      } finally { pending = false; }
    };
    setInterval(refreshOnline, 30000);
    document.addEventListener('visibilitychange', refreshOnline);
  }

  const voteEvolutionPanel = document.querySelector('[data-vote-evolution]');
  if (voteEvolutionPanel) {
    const svg = voteEvolutionPanel.querySelector('[data-vote-evolution-svg]');
    const legend = voteEvolutionPanel.querySelector('[data-vote-evolution-legend]');
    const empty = voteEvolutionPanel.querySelector('[data-vote-evolution-empty]');
    const status = voteEvolutionPanel.querySelector('[data-vote-evolution-status]');
    const source = voteEvolutionPanel.querySelector('[data-vote-evolution-data]');
    const controls = voteEvolutionPanel.querySelector('[data-vote-evolution-controls]');
    const fullPeriodButton = voteEvolutionPanel.querySelector('[data-vote-evolution-full]');
    const total = voteEvolutionPanel.querySelector('[data-vote-evolution-total]');
    const heading = voteEvolutionPanel.querySelector('[data-vote-evolution-heading]');
    const period = voteEvolutionPanel.querySelector('[data-vote-evolution-period]');
    const table = voteEvolutionPanel.querySelector('[data-vote-evolution-table]');
    const startLabel = voteEvolutionPanel.querySelector('[data-vote-evolution-start-label]');
    const endField = voteEvolutionPanel.querySelector('[data-vote-evolution-end]');
    const colors = ['#7b1f2a', '#b77a25', '#386a70', '#c36b4b', '#62527a'];
    const ns = 'http://www.w3.org/2000/svg';
    let pending = false;
    let currentEvolution = null;

    const syncEvolutionMode = () => {
      if (!controls) return;
      const hourly = controls.elements.granularity.value === 'hour';
      if (hourly) controls.elements.end.value = controls.elements.start.value;
      if (startLabel) startLabel.textContent = hourly ? 'Dia' : 'De';
      if (endField) endField.hidden = hourly;
      if (fullPeriodButton) fullPeriodButton.hidden = hourly;
    };

    const svgNode = (name, attributes = {}, text = '') => {
      const node = document.createElementNS(ns, name);
      Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, String(value)));
      if (text) node.textContent = text;
      return node;
    };
    const smoothPath = (points) => {
      if (!points.length) return '';
      if (points.length === 1) return `M ${points[0].x} ${points[0].y}`;
      let path = `M ${points[0].x} ${points[0].y}`;
      for (let i = 0; i < points.length - 1; i += 1) {
        const p0 = points[i - 1] || points[i];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[i + 2] || p2;
        const cp1x = p1.x + (p2.x - p0.x) / 6;
        const cp1y = p1.y + (p2.y - p0.y) / 6;
        const cp2x = p2.x - (p3.x - p1.x) / 6;
        const cp2y = p2.y - (p3.y - p1.y) / 6;
        path += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p2.x} ${p2.y}`;
      }
      return path;
    };
    const formatDate = (value) => {
      const parts = String(value || '').split('-');
      return parts.length === 3 ? `${parts[2]}/${parts[1]}/${parts[0]}` : value;
    };
    const renderEvolutionTable = (data) => {
      if (!table) return;
      const head = document.createElement('thead');
      const headRow = document.createElement('tr');
      ['Período', 'Votos acumulados', ...(data.finalists || []).map((item) => item.project)].forEach((label) => {
        const cell = document.createElement('th');
        cell.textContent = label;
        headRow.append(cell);
      });
      head.append(headRow);
      const body = document.createElement('tbody');
      data.days.forEach((point) => {
        const row = document.createElement('tr');
        const label = document.createElement('td');
        label.textContent = `${point.label}${point.future ? ' · previsto' : ''}`;
        row.append(label);
        const pointTotal = document.createElement('td');
        pointTotal.textContent = point.future ? '—' : Number(point.total).toLocaleString('pt-BR');
        row.append(pointTotal);
        (data.finalists || []).forEach((finalist) => {
          const cell = document.createElement('td');
          const candidate = point.candidates?.[String(finalist.id)] || {percentage: 0, votes: 0};
          cell.textContent = point.future ? '—' : `${Number(candidate.percentage).toLocaleString('pt-BR', {minimumFractionDigits: 1, maximumFractionDigits: 1})}% · ${Number(candidate.votes).toLocaleString('pt-BR')} votos`;
          row.append(cell);
        });
        body.append(row);
      });
      table.replaceChildren(head, body);
    };
    const renderEvolution = (data) => {
      if (!svg || !legend || !data?.days?.length) return;
      currentEvolution = data;
      if (heading) heading.textContent = data.granularity === 'hour' ? 'Curva dos resultados por hora' : 'Curva diária dos resultados';
      if (period) period.textContent = `${formatDate(data.range_start)} a ${formatDate(data.range_end)}`;
      if (total) total.textContent = Number(data.overall_total || 0).toLocaleString('pt-BR');
      if (controls) {
        controls.elements.granularity.value = data.granularity;
        controls.elements.start.value = data.range_start;
        controls.elements.end.value = data.range_end;
        syncEvolutionMode();
      }
      renderEvolutionTable(data);
      const width = 1000;
      const height = 380;
      const bounds = {left: 62, right: 24, top: 22, bottom: 58};
      const plotWidth = width - bounds.left - bounds.right;
      const plotHeight = height - bounds.top - bounds.bottom;
      svg.replaceChildren();
      legend.replaceChildren();

      [0, 25, 50, 75, 100].forEach((value) => {
        const y = bounds.top + plotHeight - value * plotHeight / 100;
        svg.append(svgNode('line', {x1: bounds.left, y1: y, x2: width - bounds.right, y2: y, class: 'vote-grid-line'}));
        svg.append(svgNode('text', {x: bounds.left - 12, y: y + 4, 'text-anchor': 'end', class: 'vote-axis-label'}, `${value}%`));
      });

      const labelEvery = data.granularity === 'day' ? 1 : Math.max(1, Math.ceil(data.days.length / 16));
      data.days.forEach((day, index) => {
        if (index % labelEvery !== 0 && index !== data.days.length - 1) return;
        const x = bounds.left + (data.days.length === 1 ? plotWidth / 2 : index * plotWidth / (data.days.length - 1));
        svg.append(svgNode('text', {x, y: height - 18, 'text-anchor': 'middle', class: `vote-axis-label${day.future ? ' is-future' : ''}`}, day.label));
      });

      const hasVotes = data.days.some((day) => !day.future && Number(day.total) > 0);
      if (empty) empty.hidden = hasVotes;
      (data.finalists || []).forEach((finalist, finalistIndex) => {
        const color = colors[finalistIndex % colors.length];
        const key = String(finalist.id);
        const item = document.createElement('span');
        const dot = document.createElement('i');
        dot.style.backgroundColor = color;
        item.append(dot, document.createTextNode(finalist.project));
        legend.append(item);
        const points = data.days.map((day, index) => {
          if (day.future || Number(day.total) <= 0) return null;
          const candidate = day.candidates?.[key] || {percentage: 0, votes: 0};
          return {
            x: bounds.left + (data.days.length === 1 ? plotWidth / 2 : index * plotWidth / (data.days.length - 1)),
            y: bounds.top + plotHeight - Number(candidate.percentage) * plotHeight / 100,
            day,
            candidate,
          };
        }).filter(Boolean);
        if (!points.length) return;
        svg.append(svgNode('path', {d: smoothPath(points), fill: 'none', stroke: color, class: 'vote-evolution-line'}));
        points.forEach((point) => {
          const circle = svgNode('circle', {cx: point.x, cy: point.y, r: 5, fill: color, class: 'vote-evolution-point'});
          circle.append(svgNode('title', {}, `${point.day.label} · ${finalist.project}: ${Number(point.candidate.percentage).toLocaleString('pt-BR', {minimumFractionDigits: 1, maximumFractionDigits: 1})}% · ${point.candidate.votes} votos`));
          svg.append(circle);
        });
      });
      if (status) status.textContent = `Atualização automática a cada 30 segundos · ${data.updated_at}`;
    };

    try { renderEvolution(JSON.parse(source?.textContent || '{}')); } catch (_) { /* A tabela permanece disponível. */ }
    const refreshEvolution = async () => {
      if (document.visibilityState !== 'visible' || pending) return;
      pending = true;
      try {
        const params = new URLSearchParams({
          granularity: controls?.elements.granularity.value || currentEvolution?.granularity || 'day',
          start: controls?.elements.start.value || currentEvolution?.range_start || '',
          end: controls?.elements.end.value || currentEvolution?.range_end || '',
        });
        const response = await fetch(`${voteEvolutionPanel.dataset.evolutionUrl}?${params}`, {credentials: 'same-origin', cache: 'no-store'});
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error('indisponível');
        renderEvolution(data.evolution);
      } catch (_) {
        if (status) status.textContent = 'Sem atualização ao vivo; tentando novamente…';
      } finally { pending = false; }
    };
    controls?.addEventListener('submit', (event) => {
      event.preventDefault();
      syncEvolutionMode();
      refreshEvolution();
    });
    controls?.elements.granularity.addEventListener('change', syncEvolutionMode);
    controls?.elements.start.addEventListener('change', syncEvolutionMode);
    fullPeriodButton?.addEventListener('click', () => {
      if (!currentEvolution || !controls) return;
      controls.elements.start.value = currentEvolution.starts_at;
      controls.elements.end.value = currentEvolution.ends_at;
      refreshEvolution();
    });
    setInterval(refreshEvolution, 30000);
    document.addEventListener('visibilitychange', refreshEvolution);
  }

  const dialog = document.querySelector('[data-vote-dialog]');
  if (dialog) {
    let selectedId = null;
    let selectedSocialUrl = '';
    let captchaId = null;
    const feedback = dialog.querySelector('[data-vote-feedback]');
    const confirmButton = dialog.querySelector('[data-confirm-vote]');
    const captchaInput = dialog.querySelector('#vote-captcha');
    const termsInput = dialog.querySelector('[data-vote-terms]');

    const loadCaptcha = async () => {
      feedback.textContent = '';
      captchaInput.value = '';
      dialog.querySelector('[data-captcha-question]').textContent = 'Carregando verificação…';
      try {
        const response = await fetch('/api/captcha/vote', { headers: { Accept: 'application/json' } });
        const data = await response.json();
        captchaId = data.challenge.id;
        dialog.querySelector('[data-captcha-question]').textContent = data.challenge.question;
      } catch (_) {
        feedback.textContent = 'Não foi possível carregar a verificação. Feche e tente novamente.';
      }
    };

    const submitVoteButton = document.querySelector('[data-submit-vote]');
    document.querySelectorAll('[data-vote-choice]').forEach((button) => {
      button.addEventListener('click', () => {
        selectedId = button.dataset.voteChoice;
        selectedSocialUrl = button.dataset.voteSocial || '';
        document.querySelectorAll('[data-vote-choice]').forEach((item) => {
          const active = item === button;
          item.setAttribute('aria-pressed', String(active));
          item.closest('.candidate-card')?.classList.toggle('selected', active);
        });
        if (submitVoteButton) submitVoteButton.disabled = false;
      });
    });

    submitVoteButton?.addEventListener('click', async () => {
      if (!selectedId) return;
      const chosen = document.querySelector(`[data-vote-choice="${CSS.escape(selectedId)}"]`);
      dialog.querySelector('[data-selected-project]').textContent = chosen?.dataset.voteLabel || '';
      dialog.querySelector('[data-vote-confirm-step]').hidden = false;
      dialog.querySelector('[data-vote-success]').hidden = true;
      if (termsInput) termsInput.checked = false;
      dialog.showModal();
      await loadCaptcha();
      captchaInput.focus();
    });

    dialog.querySelector('[data-change-choice]')?.addEventListener('click', () => dialog.close());
    confirmButton?.addEventListener('click', async () => {
      if (!selectedId || !captchaId || captchaInput.value.trim() === '' || !termsInput?.checked) {
        feedback.textContent = !termsInput?.checked
          ? 'Aceite o regulamento e o aviso de privacidade para confirmar.'
          : 'Responda à verificação para confirmar.';
        return;
      }
      const body = new FormData();
      body.append('_csrf', csrfToken);
      body.append('finalist_id', selectedId);
      body.append('captcha_id', captchaId);
      body.append('captcha_answer', captchaInput.value);
      body.append('terms_accepted', '1');
      body.append('terms_version', '2026-11-20');
      body.append('website', dialog.querySelector('[data-honeypot]')?.value || '');
      confirmButton.disabled = true;
      confirmButton.textContent = 'Confirmando…';
      feedback.textContent = '';
      try {
        const response = await fetch('/api/vote', { method: 'POST', body, headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) {
          feedback.textContent = data.message || 'Não foi possível confirmar.';
          if (!data.duplicate) await loadCaptcha();
          if (data.duplicate && data.receipt) {
            dialog.querySelector('[data-vote-confirm-step]').hidden = true;
            dialog.querySelector('[data-vote-success]').hidden = false;
            dialog.querySelector('[data-success-message]').textContent = data.message;
            dialog.querySelector('[data-receipt-code]').textContent = data.receipt;
            const auditLink = dialog.querySelector('[data-audit-link]');
            if (auditLink) auditLink.href = `/auditoria?codigo=${encodeURIComponent(data.receipt)}`;
            const socialLink = dialog.querySelector('[data-social-link]');
            if (socialLink) socialLink.href = selectedSocialUrl || '#';
          }
          return;
        }
        dialog.querySelector('[data-vote-confirm-step]').hidden = true;
        dialog.querySelector('[data-vote-success]').hidden = false;
        dialog.querySelector('[data-success-message]').textContent = data.message;
        dialog.querySelector('[data-receipt-code]').textContent = data.receipt;
        const auditLink = dialog.querySelector('[data-audit-link]');
        if (auditLink) auditLink.href = `/auditoria?codigo=${encodeURIComponent(data.receipt)}`;
        const socialLink = dialog.querySelector('[data-social-link]');
        if (socialLink) socialLink.href = selectedSocialUrl || '#';
        document.querySelectorAll('[data-vote-choice]').forEach((item) => { item.disabled = true; });
      } catch (_) {
        feedback.textContent = 'A conexão falhou. Seu voto não foi confirmado; tente novamente.';
      } finally {
        confirmButton.disabled = false;
        confirmButton.textContent = 'Confirmar voto';
      }
    });
  }

  const multistep = document.querySelector('[data-multistep-form]');
  if (multistep) {
    const form = multistep.querySelector('[data-registration-form]');
    const steps = [...form.querySelectorAll('[data-form-step]')];
    const indicators = [...multistep.querySelectorAll('[data-step-indicator]')];
    const back = form.querySelector('[data-step-back]');
    const next = form.querySelector('[data-step-next]');
    const submit = form.querySelector('[data-form-submit]');
    let current = 0;
    let registrationCaptchaLoaded = false;

    const validStep = (index) => {
      const fields = [...steps[index].querySelectorAll('input,select,textarea')];
      for (const field of fields) {
        if (!field.checkValidity()) {
          field.reportValidity();
          return false;
        }
      }
      if (index === 2) {
        const photos = form.querySelector('input[name="project_photos[]"]');
        if (photos.files.length < 3 || photos.files.length > 5) {
          photos.setCustomValidity('Selecione de 3 a 5 fotos.');
          photos.reportValidity();
          photos.setCustomValidity('');
          return false;
        }
      }
      return true;
    };

    const loadRegistrationCaptcha = async () => {
      if (registrationCaptchaLoaded) return;
      const label = form.querySelector('[data-registration-captcha-question]');
      try {
        const response = await fetch('/api/captcha/inscricao', { headers: { Accept: 'application/json' } });
        const data = await response.json();
        form.querySelector('[data-registration-captcha-id]').value = data.challenge.id;
        label.textContent = data.challenge.question;
        registrationCaptchaLoaded = true;
      } catch (_) {
        label.textContent = 'Falha ao carregar. Volte uma etapa e tente novamente.';
      }
    };

    const show = (index) => {
      current = Math.max(0, Math.min(steps.length - 1, index));
      steps.forEach((step, i) => { step.hidden = i !== current; });
      indicators.forEach((item, i) => {
        item.classList.toggle('active', i === current);
        item.classList.toggle('complete', i < current);
      });
      back.hidden = current === 0;
      next.hidden = current === steps.length - 1;
      submit.hidden = current !== steps.length - 1;
      if (current === steps.length - 1) loadRegistrationCaptcha();
      steps[current].querySelector('input,select,textarea')?.focus({ preventScroll: true });
      multistep.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    next.addEventListener('click', () => { if (validStep(current)) show(current + 1); });
    back.addEventListener('click', () => show(current - 1));
    form.addEventListener('submit', (event) => {
      for (let i = 0; i < steps.length; i += 1) {
        if (!validStep(i)) {
          event.preventDefault();
          show(i);
          return;
        }
      }
      submit.disabled = true;
      submit.textContent = 'Enviando…';
    });
    show(0);
  }

  document.querySelectorAll('[data-load-files]').forEach((button) => {
    button.addEventListener('click', async () => {
      const id = button.dataset.loadFiles;
      const target = document.querySelector(`[data-files-for="${id}"]`);
      button.disabled = true;
      target.textContent = 'Carregando…';
      try {
        const response = await fetch(`/admin/inscricoes/${id}/arquivos`, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        target.innerHTML = '';
        data.files.forEach((file) => {
          const link = document.createElement('a');
          link.href = `/admin/arquivos/${file.id}/download`;
          link.textContent = `${file.file_kind === 'invoice' ? 'Nota fiscal' : 'Foto'} · ${file.original_name}`;
          link.style.display = 'block';
          target.appendChild(link);
        });
        if (!data.files.length) target.textContent = 'Sem arquivos.';
      } catch (_) {
        target.textContent = 'Falha ao listar arquivos.';
        button.disabled = false;
      }
    });
  });

  const regionalMap = document.querySelector('[data-regional-map]');
  if (regionalMap) {
    const dataNode = regionalMap.querySelector('[data-regional-vote-data]');
    const mapRoot = regionalMap.querySelector('[data-brazil-map-inline]');
    const nameNode = regionalMap.querySelector('[data-region-name]');
    const kickerNode = regionalMap.querySelector('[data-region-kicker]');
    const summaryNode = regionalMap.querySelector('[data-region-summary]');
    const candidatesNode = regionalMap.querySelector('[data-region-candidates]');
    const citiesNode = regionalMap.querySelector('[data-region-cities]');
    let regionalData = null;
    try { regionalData = JSON.parse(dataNode?.textContent || '{}'); } catch (_) { regionalData = {}; }
    const states = regionalData.states || {};
    const volumes = Object.values(states).map((state) => Number(state.total) || 0);
    const maxVolume = Math.max(1, ...volumes);
    const palette = ['#e8e2da', '#d8bdb8', '#bd8781', '#9d554f', '#76241f'];
    let selectedUf = regionalData.top_state?.uf || Object.keys(states)[0] || '';

    const percentage = (value) => `${Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;
    const clear = (node) => { while (node?.firstChild) node.removeChild(node.firstChild); };
    const candidateRow = (candidate, isLeader, isTie) => {
      const row = document.createElement('div');
      row.className = 'regional-candidate-row';
      const heading = document.createElement('div');
      const title = document.createElement('strong');
      title.textContent = candidate.project;
      const value = document.createElement('b');
      value.textContent = percentage(candidate.percentage);
      heading.append(title, value);
      if (isLeader) {
        const badge = document.createElement('small');
        badge.textContent = isTie ? 'Empate na liderança' : 'Líder';
        heading.append(badge);
      }
      const track = document.createElement('span');
      const fill = document.createElement('i');
      fill.style.width = `${Math.max(0, Math.min(100, Number(candidate.percentage) || 0))}%`;
      track.appendChild(fill);
      row.append(heading, track);
      return row;
    };
    const renderState = (uf) => {
      const state = states[uf];
      if (!state) return;
      selectedUf = uf;
      kickerNode.textContent = `${state.macroregion} · ${state.uf} · ${Number(state.total).toLocaleString('pt-BR')} votos válidos`;
      nameNode.textContent = state.name;
      summaryNode.textContent = state.tie
        ? 'Há empate na liderança neste estado.'
        : state.leader
          ? `${state.leader.project} lidera com ${percentage(state.leader.percentage)} dos votos válidos do estado.`
          : 'Ainda não há votos válidos neste estado no período selecionado.';
      clear(candidatesNode);
      state.candidates.forEach((candidate) => {
        const leads = Boolean(state.leader && candidate.votes === state.leader.votes);
        candidatesNode.appendChild(candidateRow(candidate, leads, state.tie));
      });
      clear(citiesNode);
      const cityTitle = document.createElement('h4');
      cityTitle.textContent = 'Principais cidades de origem';
      citiesNode.appendChild(cityTitle);
      if (!state.cities.length) {
        const empty = document.createElement('p');
        empty.textContent = 'Cidade não informada para os votos deste estado.';
        citiesNode.appendChild(empty);
      } else {
        const list = document.createElement('ol');
        state.cities.slice(0, 5).forEach((city) => {
          const item = document.createElement('li');
          const leader = city.tie ? 'empate' : (city.leader?.project || 'sem líder');
          item.textContent = `${city.name}: ${Number(city.total).toLocaleString('pt-BR')} · ${leader}`;
          list.appendChild(item);
        });
        citiesNode.appendChild(list);
      }
      regionalMap.querySelectorAll('[data-select-uf]').forEach((button) => button.classList.toggle('is-selected', button.dataset.selectUf === uf));
      const selectedShape = mapRoot?.querySelector(`#${uf}`);
      const highlightLayer = mapRoot?.querySelector('[data-map-highlight]');
      clear(highlightLayer);
      if (selectedShape && highlightLayer) {
        const highlight = selectedShape.cloneNode(true);
        highlight.removeAttribute('id');
        highlight.removeAttribute('tabindex');
        highlight.removeAttribute('role');
        highlight.removeAttribute('aria-label');
        highlight.removeAttribute('data-vote-map-state');
        highlight.classList.add('is-selected', 'map-state-highlight');
        highlightLayer.appendChild(highlight);
      }
    };

    const prepareMap = () => {
      if (!mapRoot) return;
      const mapSvg = mapRoot.querySelector('svg');
      if (mapSvg && !mapSvg.querySelector('[data-map-highlight]')) {
        const highlightLayer = document.createElementNS('http://www.w3.org/2000/svg', 'g');
        highlightLayer.setAttribute('data-map-highlight', '');
        highlightLayer.setAttribute('aria-hidden', 'true');
        highlightLayer.style.pointerEvents = 'none';
        mapSvg.appendChild(highlightLayer);
      }
      ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'].forEach((uf) => {
        const shape = mapRoot.querySelector(`#${uf}`);
        if (!shape) return;
        const state = states[uf];
        const volume = Number(state?.total) || 0;
        const bucket = volume > 0 ? Math.max(1, Math.ceil((volume / maxVolume) * 4)) : 0;
        shape.style.fill = palette[bucket];
        shape.setAttribute('data-vote-map-state', '');
        shape.setAttribute('tabindex', '0');
        shape.setAttribute('role', 'button');
        shape.setAttribute('aria-label', state ? `${state.name}, ${state.total} votos válidos` : `${uf}, sem votos no período`);
        const select = () => state && renderState(uf);
        shape.addEventListener('mouseenter', select);
        shape.addEventListener('focus', select);
        shape.addEventListener('click', select);
        shape.addEventListener('keydown', (event) => {
          if (!['Enter', ' '].includes(event.key)) return;
          event.preventDefault();
          select();
        });
      });
      if (selectedUf) renderState(selectedUf);
    };
    prepareMap();
    regionalMap.querySelectorAll('[data-select-uf]').forEach((button) => button.addEventListener('click', () => {
      renderState(button.dataset.selectUf);
      regionalMap.querySelector('[data-region-detail]')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }));
    if (selectedUf) renderState(selectedUf);
  }

  const adminLiveRanking = document.querySelector('[data-live-admin-ranking]');
  if (adminLiveRanking) {
    const integer = new Intl.NumberFormat('pt-BR');
    const decimal = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    const refreshAdminRanking = async () => {
      try {
        const response = await fetch('/admin/api/ranking', { credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) return;
        const data = await response.json();
        if (!data.ok) return;
        const list = adminLiveRanking.querySelector('.ranking-list');
        data.ranking.forEach((candidate, index) => {
          const row = list?.querySelector(`[data-ranking-id="${candidate.id}"]`);
          if (!row) return;
          row.querySelector('[data-rank-position]').textContent = `${index + 1}º`;
          row.querySelector('[data-rank-percentage]').textContent = `${decimal.format(candidate.percentage)}%`;
          row.querySelector('[data-rank-votes]').textContent = `${integer.format(candidate.vote_count)} votos`;
          row.querySelector('.rank-track i').style.width = `${candidate.percentage}%`;
          list.appendChild(row);
        });
        const total = document.querySelector('[data-admin-valid-total]');
        if (total) total.textContent = integer.format(data.total);
        const updated = adminLiveRanking.querySelector('[data-ranking-updated]');
        if (updated) updated.textContent = `Atualizado às ${data.updated_at}`;
      } catch (_) { /* Mantém a última apuração disponível. */ }
    };
    window.setInterval(refreshAdminRanking, 15000);
  }

  const waitingResults = document.querySelector('.results-waiting[data-results-mode]');
  if (waitingResults) {
    const refreshWaitingPhase = async () => {
      try {
        const response = await fetch('/api/resultados', { credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) return;
        const data = await response.json();
        if (data.ok && data.mode !== waitingResults.dataset.resultsMode) window.location.reload();
      } catch (_) { /* Mantém o aviso e tenta novamente no próximo ciclo. */ }
    };
    window.setInterval(refreshWaitingPhase, 15000);
  }

  const publicResults = document.querySelector('[data-live-public-results]');
  if (publicResults) {
    const currentMode = publicResults.dataset.resultsMode || 'live';
    const integer = new Intl.NumberFormat('pt-BR');
    const decimal = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    const mapSection = publicResults.querySelector('[data-public-regional-map]');
    const mapRoot = mapSection?.querySelector('[data-public-brazil-map]');
    const nameNode = mapSection?.querySelector('[data-public-region-name]');
    const kickerNode = mapSection?.querySelector('[data-public-region-kicker]');
    const summaryNode = mapSection?.querySelector('[data-public-region-summary]');
    const candidatesNode = mapSection?.querySelector('[data-public-region-candidates]');
    let regionalData = {};
    let selectedRegion = 'Sudeste';
    try { regionalData = JSON.parse(publicResults.querySelector('[data-public-regional-data]')?.textContent || '{}'); } catch (_) {}
    const regionColors = { 'Norte': '#9d554f', 'Nordeste': '#c97b6d', 'Centro-Oeste': '#76241f', 'Sudeste': '#a7342b', 'Sul': '#d9a49b' };
    const clear = (node) => { while (node?.firstChild) node.removeChild(node.firstChild); };
    const renderRegion = (regionName) => {
      const region = regionalData.macroregions?.[regionName];
      if (!region) return;
      selectedRegion = regionName;
      kickerNode.textContent = 'Percentuais regionais';
      nameNode.textContent = region.name;
      summaryNode.textContent = region.tie ? 'Há empate na liderança nesta região.' : (region.leader ? `${region.leader.project} lidera com ${decimal.format(region.leader.percentage)}%.` : 'Ainda não há votos válidos localizados nesta região.');
      clear(candidatesNode);
      region.candidates.forEach((candidate) => {
        const row = document.createElement('div');
        row.className = 'public-region-candidate';
        const head = document.createElement('div');
        const title = document.createElement('strong');
        title.textContent = candidate.project;
        const value = document.createElement('b');
        value.textContent = `${decimal.format(candidate.percentage)}%`;
        head.append(title, value);
        const track = document.createElement('span');
        const fill = document.createElement('i');
        fill.style.width = `${Math.max(0, Math.min(100, Number(candidate.percentage) || 0))}%`;
        track.appendChild(fill);
        row.append(head, track);
        candidatesNode.appendChild(row);
      });
      mapSection.querySelectorAll('[data-public-region]').forEach((button) => button.classList.toggle('is-active', button.dataset.publicRegion === regionName));
      mapRoot?.querySelectorAll('[data-public-map-state]').forEach((shape) => shape.classList.toggle('is-active', shape.dataset.region === regionName));
    };
    const preparePublicMap = () => {
      Object.values(regionalData.states || {}).forEach((state) => {
        const shape = mapRoot?.querySelector(`#${state.uf}`);
        if (!shape) return;
        shape.style.fill = regionColors[state.macroregion] || '#ddd4ca';
        shape.dataset.region = state.macroregion;
        shape.setAttribute('data-public-map-state', '');
        shape.setAttribute('tabindex', '0');
        shape.setAttribute('role', 'button');
        shape.setAttribute('aria-label', `${state.name}, região ${state.macroregion}`);
        const select = () => renderRegion(state.macroregion);
        shape.addEventListener('mouseenter', select);
        shape.addEventListener('focus', select);
        shape.addEventListener('click', select);
        shape.addEventListener('keydown', (event) => {
          if (!['Enter', ' '].includes(event.key)) return;
          event.preventDefault();
          select();
        });
      });
    };
    const updatePublicRanking = (ranking) => {
      const list = publicResults.querySelector('[data-public-ranking]');
      ranking.forEach((candidate, index) => {
        const row = list?.querySelector(`[data-ranking-id="${candidate.id}"]`);
        if (!row) return;
        row.querySelector(':scope > span').textContent = `${index + 1}º`;
        row.querySelector('em').textContent = `${decimal.format(candidate.percentage)}%`;
        row.querySelector('i b').style.width = `${candidate.percentage}%`;
        const absolute = row.querySelector('[data-public-absolute]');
        if (absolute && Number.isFinite(Number(candidate.votes))) absolute.textContent = `${integer.format(candidate.votes)} votos válidos`;
        list.appendChild(row);
      });
    };
    preparePublicMap();
    mapSection?.querySelectorAll('[data-public-region]').forEach((button) => button.addEventListener('click', () => renderRegion(button.dataset.publicRegion)));
    renderRegion(regionalData.macroregions?.[selectedRegion] ? selectedRegion : Object.keys(regionalData.macroregions || {})[0]);
    const refreshPublicResults = async () => {
      try {
        const response = await fetch('/api/resultados', { credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok) return;
        const data = await response.json();
        if (!data.ok) return;
        if (data.mode !== currentMode) {
          window.location.reload();
          return;
        }
        updatePublicRanking(data.ranking);
        regionalData = data.regional;
        renderRegion(regionalData.macroregions?.[selectedRegion] ? selectedRegion : Object.keys(regionalData.macroregions || {})[0]);
        const updated = publicResults.querySelector('[data-public-results-updated]');
        if (updated) updated.textContent = data.updated_at;
      } catch (_) { /* Mantém os últimos percentuais exibidos. */ }
    };
    window.setInterval(refreshPublicResults, 15000);
  }
})();
