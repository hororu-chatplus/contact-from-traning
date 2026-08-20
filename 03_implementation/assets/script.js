// お問い合わせフォームのクライアント側バリデーション
// サーバー側でも同じ内容を必ず再チェックする（要件 F-02）。ここでの検証はUXのための補助に過ぎない。
(function () {
  const form = document.querySelector('form[action="/index.php"]');
  if (!form) return;

  const messages = {
    company_name: { required: '会社名を入力してください', max: '会社名は50文字以内で入力してください' },
    department: { max: '部署名は50文字以内で入力してください' },
    position: { max: '役職は50文字以内で入力してください' },
    name: { required: '氏名を入力してください', max: '氏名は50文字以内で入力してください' },
    email: {
      required: 'メールアドレスを入力してください',
      format: '正しい形式のメールアドレスを入力してください',
      max: 'メールアドレスは256文字以内で入力してください',
    },
    phone: { required: '電話番号を入力してください', pattern: '電話番号は数字とハイフンのみで入力してください' },
    contact_role: { required: 'ご担当を選択してください' },
    chatplus_status: { required: 'ChatPlusについてを選択してください' },
    service_type: { required: 'サービス項目を選択してください' },
    content: { required: '問い合わせ内容を入力してください', max: '問い合わせ内容は1000文字以内で入力してください' },
    privacy_consent: { required: 'プライバシーポリシーへの同意にチェックを入れてください' },
  };

  function showError(field, message) {
    field.classList.add('error');
    let p = field.parentElement.querySelector('.js-error');
    if (!p) {
      p = document.createElement('p');
      p.className = 'error-message js-error';
      field.parentElement.appendChild(p);
    }
    p.textContent = message;
  }

  function clearError(field) {
    field.classList.remove('error');
    const p = field.parentElement.querySelector('.js-error');
    if (p) p.remove();
  }

  function validateField(field) {
    const name = field.name;
    const msg = messages[name];
    if (!msg) return true;

    if (field.type === 'checkbox') {
      if (field.dataset.required === 'true' && !field.checked) {
        showError(field, msg.required);
        return false;
      }
      clearError(field);
      return true;
    }

    const value = field.value.trim();

    if (field.dataset.required === 'true' && value === '') {
      showError(field, msg.required);
      return false;
    }
    if (value === '') {
      clearError(field);
      return true;
    }
    if (field.dataset.max && [...value].length > parseInt(field.dataset.max, 10)) {
      showError(field, msg.max);
      return false;
    }
    if (field.dataset.pattern && !new RegExp(field.dataset.pattern).test(value)) {
      showError(field, msg.pattern);
      return false;
    }
    if (name === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
      showError(field, msg.format);
      return false;
    }

    clearError(field);
    return true;
  }

  const fields = form.querySelectorAll('[name]');
  fields.forEach((field) => {
    const eventName = field.type === 'checkbox' ? 'change' : 'blur';
    field.addEventListener(eventName, () => validateField(field));
  });

  form.addEventListener('submit', (event) => {
    let valid = true;
    fields.forEach((field) => {
      if (!validateField(field)) valid = false;
    });
    if (!valid) {
      event.preventDefault();
    }
  });
})();

// 文字数カウンター（data-max属性を持つ入力欄すべてに適用）
(function () {
  document.querySelectorAll('[data-max]').forEach((field) => {
    const max = parseInt(field.dataset.max, 10);
    if (!max) return;

    const counter = document.createElement('p');
    counter.className = 'char-counter';
    field.insertAdjacentElement('afterend', counter);

    const update = () => {
      counter.textContent = [...field.value].length + ' / ' + max + '文字';
    };
    field.addEventListener('input', update);
    update();
  });
})();

// 二重送信防止: フォーム送信時に送信ボタンを無効化する
// （index.phpのクライアント側チェックでpreventDefault()された場合は無効化しない）
(function () {
  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (event.defaultPrevented) return;
      const btn = form.querySelector('button[type="submit"]');
      if (btn && !btn.disabled) {
        btn.disabled = true;
        btn.textContent = '送信中...';
      }
    });
  });
})();
