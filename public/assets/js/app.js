(() => {
  'use strict';
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const navToggle = document.querySelector('.nav-toggle');
  const nav = document.querySelector('.main-nav');
  navToggle?.addEventListener('click', () => {
    const open = nav?.classList.toggle('open') ?? false;
    navToggle.setAttribute('aria-expanded', String(open));
  });

  const dialog = document.querySelector('[data-vote-dialog]');
  if (dialog) {
    let selectedId = null;
    let selectedSocialUrl = '';
    let captchaId = null;
    const feedback = dialog.querySelector('[data-vote-feedback]');
    const confirmButton = dialog.querySelector('[data-confirm-vote]');
    const captchaInput = dialog.querySelector('#vote-captcha');

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
      dialog.showModal();
      await loadCaptcha();
      captchaInput.focus();
    });

    dialog.querySelector('[data-change-choice]')?.addEventListener('click', () => dialog.close());
    confirmButton?.addEventListener('click', async () => {
      if (!selectedId || !captchaId || captchaInput.value.trim() === '') {
        feedback.textContent = 'Responda à verificação para confirmar.';
        return;
      }
      const body = new FormData();
      body.append('_csrf', csrfToken);
      body.append('finalist_id', selectedId);
      body.append('captcha_id', captchaId);
      body.append('captcha_answer', captchaInput.value);
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
})();
