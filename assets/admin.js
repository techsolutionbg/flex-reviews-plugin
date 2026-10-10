export function createPage(host) {
  const {React, components: C, request, toast, icons} = host;
  const h = React.createElement, base = '/admin/plugins/flex/reviews';
  const states = {pending: 'Чакащи', approved: 'Одобрени', spam: 'Спам', trash: 'Кошче'};
  function Select({value, options, onChange, label, hint}) {
    return h('label', {className: 'react-form-field'}, label,
      h(C.DropdownMenu, {ariaLabel: label, trigger: h(React.Fragment, null, h('span', null, options[value] ?? 'Избери'), h(C.DropdownChevron)), triggerClassName: 'react-form-dropdown'},
        ...Object.entries(options).map(([key, label]) => h(C.DropdownOption, {key, selected: String(value) === key, onClick: () => onChange(key)}, label))),
      hint && h('small', {className: 'react-field-hint'}, hint));
  }
  const sections = [
    ['Общи', ['enabled', 'ratings', 'per_page', 'sort']],
    ['Достъп', ['guests', 'one_per_product', 'user_replies', 'guest_replies']],
    ['Модерация', ['moderation', 'trusted_authors', 'replies', 'reply_moderation', 'replies_per_page']],
    ['Защита', ['min_length', 'max_length', 'interval_seconds', 'max_links']],
    ['Известия', ['notify_admin', 'admin_email', 'notify_reply']],
    ['Данни', ['delete_on_uninstall']],
  ];
  const labels = {
    notify_admin: 'Имейл при нов коментар',
    admin_email: 'Имейл на администратора',
    notify_reply: 'Известяване при отговор (със съгласие на автора)',
    enabled: 'Включени коментари',
    ratings: 'Оценки със звезди (по желание)',
    guests: 'Коментари от гости',
    one_per_product: 'Един коментар на автор за продукт',
    moderation: 'Предварително одобрение',
    trusted_authors: 'Автоматично одобрение за регистрирани автори с одобрен коментар',
    replies: 'Включени разговори и отговори',
    user_replies: 'Отговори от потребители',
    guest_replies: 'Отговори от гости',
    reply_moderation: 'Предварително одобрение на потребителските отговори',
    replies_per_page: 'Първоначално показани отговори',
    per_page: 'Коментари на страница',
    sort: 'Подредба',
    min_length: 'Минимална дължина',
    max_length: 'Максимална дължина',
    interval_seconds: 'Интервал между коментарите (секунди)',
    max_links: 'Максимален брой връзки',
    delete_on_uninstall: 'Изтриване на всички коментари при деинсталиране',
  };
  const hints = {
    enabled: 'Показва секцията с коментари на продуктовите страници. При изключване публичните коментари се скриват, без да се изтриват.',
    ratings: 'Позволява на автора да избере оценка от 1 до 5 звезди. Полето остава по желание и не е задължително за публикуване.',
    per_page: 'Колко одобрени коментара се показват на една страница. Допустимо е между 1 и 50.',
    sort: 'Редът на одобрените коментари: най-новите първо или най-старите първо.',
    guests: 'Позволява коментар без вход в профил. При изключване могат да пишат само влезли клиенти.',
    one_per_product: 'Ограничава автора до един основен коментар за продукт. Отговорите в разговора не се броят.',
    user_replies: 'Влезли клиенти могат да отговарят в разговор под коментар.',
    guest_replies: 'Гости без профил могат да отговарят в разговор. Изисква включени коментари от гости.',
    moderation: 'Новите коментари остават чакащи, докато не ги одобрите. Без тази опция се публикуват веднага.',
    trusted_authors: 'Регистриран автор с вече одобрен коментар се публикува без повторно одобрение.',
    replies: 'Включва разговорите под коментарите: отговори от магазина и от посетители.',
    reply_moderation: 'Потребителските отговори чакат одобрение преди да се покажат публично. Отговорите от магазина се публикуват веднага.',
    replies_per_page: 'Колко отговора се показват първоначално под коментар. Останалите се зареждат с „Покажи още“. Между 1 и 50.',
    min_length: 'Минимален брой символи в текста на коментар или отговор. Трябва да е поне 1 и не повече от максималната дължина.',
    max_length: 'Максимален брой символи в текста. Допустимо е до 10000 и не по-малко от минималната дължина.',
    interval_seconds: 'Минимално време между два коментара от един и същ адрес. Допустимо е между 10 секунди и 86400 (едно денонощие).',
    max_links: 'Колко уеб адреса може да съдържа един коментар. 0 забранява връзките. Максимумът е 10.',
    notify_admin: 'Изпраща имейл при нов коментар. Изисква валиден имейл на администратора и работеща поща на сайта.',
    admin_email: 'Адресът, на който се изпращат известията за нови коментари. Задължителен, когато известията са включени.',
    notify_reply: 'Известява автора по имейл, ако е поискал известие и магазинът или друг потребител отговори в разговора.',
    delete_on_uninstall: 'При деинсталиране на плъгина изтрива всички коментари и настройките. По подразбиране данните се запазват.',
  };

  function SettingsPage({onBack, navigate}) {
    const [values, setValues] = React.useState(null);
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState('');
    React.useEffect(() => {
      request(base + '/settings').then((d) => setValues(d.settings)).catch((e) => setError(e.message));
    }, []);
    async function save(e) {
      e.preventDefault();
      setBusy(true);
      try {
        const d = await request(base + '/settings', 'PUT', values);
        setValues(d.settings);
        toast?.('Настройките са запазени.');
      } catch (err) {
        setError(err.message);
      } finally {
        setBusy(false);
      }
    }
    return h(React.Fragment, null,
      h('div', {className: 'react-page-heading'},
        h('div', null,
          h('h1', null, 'Настройки на коментарите'),
          h(C.Breadcrumbs, {
            items: [
              {label: 'Коментари', onClick: onBack},
              {label: 'Настройки на коментарите'},
            ],
            onHomeClick: () => navigate?.('/'),
          }))),
      error && h('p', {role: 'alert'}, error),
      !values
        ? h('p', {role: 'status'}, error ? 'Настройките не могат да бъдат заредени.' : 'Зареждане…')
        : h('form', {onSubmit: save, className: 'react-edit-form reviews-settings'},
            h('fieldset', {disabled: busy},
              ...sections.map(([title, keys]) => {
                const fields = keys.map((key) => {
                  if (typeof values[key] === 'boolean') {
                    return h(C.CheckboxField, {
                      key,
                      checked: values[key],
                      onChange: (e) => setValues({...values, [key]: e.target.checked}),
                      label: labels[key],
                      description: hints[key],
                    });
                  }
                  if (key === 'sort') {
                    return h(Select, {
                      key,
                      label: labels[key],
                      value: values[key],
                      options: {newest: 'Най-новите първо', oldest: 'Най-старите първо'},
                      onChange: (v) => setValues({...values, [key]: v}),
                      hint: hints[key],
                    });
                  }
                  return h('label', {key, className: 'react-form-field'}, labels[key],
                    h(C.Input, {
                      type: key === 'admin_email' ? 'email' : 'number',
                      min: 0,
                      value: values[key],
                      onChange: (e) => setValues({
                        ...values,
                        [key]: key === 'admin_email' ? e.target.value : Number(e.target.value),
                      }),
                    }),
                    hints[key] && h('small', {className: 'react-field-hint'}, hints[key]));
                });
                return h(C.CollapsibleSection, {
                  key: title,
                  title,
                  icon: icons.Settings,
                  storageKey: 'reviews-settings-' + title,
                  defaultOpen: true,
                }, h('div', {
                  className: 'react-form-grid' + (title === 'Известия' ? ' reviews-settings-notifications' : ''),
                }, ...fields));
              }),
              h('div', {className: 'react-form-actions'},
                h(C.LoadingButton, {type: 'submit', loading: busy, disabled: busy, icon: h(icons.Save)}, 'Запази настройките')))));
  }

  function CommentsPage({navigate, onOpenSettings}) {
    const [status, setStatus] = React.useState('pending');
    const [search, setSearch] = React.useState('');
    const [page, setPage] = React.useState(1);
    const [data, setData] = React.useState(null);
    const [selected, setSelected] = React.useState([]);
    const [busy, setBusy] = React.useState(false);
    const [error, setError] = React.useState('');
    const [revision, refresh] = React.useState(0);
    const [editor, setEditor] = React.useState(null);
    const [reply, setReply] = React.useState('');
    const [conversation, setConversation] = React.useState(null);
    const [threadPage, setThreadPage] = React.useState(1);
    React.useEffect(() => {
      let alive = true;
      const abort = new AbortController();
      setData(null);
      setSelected([]);
      const timer = setTimeout(() => {
        request(base + '/comments?status=' + status + '&q=' + encodeURIComponent(search) + '&page=' + page, 'GET', undefined, abort.signal)
          .then((d) => { if (alive) { setData(d); setError(''); } })
          .catch((e) => { if (alive) setError(e.message); });
      }, search ? 300 : 0);
      return () => { alive = false; clearTimeout(timer); abort.abort(); };
    }, [status, search, page, revision]);
    React.useEffect(() => {
      if (!editor) return;
      let alive = true;
      setConversation(null);
      request(base + '/thread/' + editor.id + '?page=' + threadPage)
        .then((d) => { if (alive) setConversation(d); })
        .catch((e) => { if (alive) setError(e.message); });
      return () => { alive = false; };
    }, [editor?.id, threadPage, revision]);
    async function action(ids, next, replyText) {
      setBusy(true);
      setError('');
      try {
        await request(base + '/comments', 'POST', {ids, status: next, ...(replyText === undefined ? {} : {reply: replyText})});
        setReply('');
        refresh((v) => v + 1);
      } catch (e) {
        setError(e.message);
      } finally {
        setBusy(false);
      }
    }
    const btn = (label, next, ids = selected) => h(C.LoadingButton, {key: next, type: 'button', loading: busy, disabled: busy || !ids.length, onClick: () => action(ids, next)}, label);
    function edit(row) {
      setSelected([]);
      setEditor(row);
      setReply('');
      setThreadPage(1);
    }
    const columns = [
      {key: 'name', label: 'Автор', sortable: true, render: (r) => h('div', null, h('a', {className: 'react-page-link', href: '#comment-' + r.id, onClick: (e) => { e.preventDefault(); edit(r); }}, r.name), h('small', {style: {display: 'block'}}, r.email))},
      {key: 'kind', label: 'Тип', render: (r) => r.root_id ? (r.is_store ? 'Отговор от магазина' : 'Отговор') : 'Коментар'},
      {key: 'body', label: 'Коментар', render: (r) => h('span', {style: {display: 'block', maxWidth: '420px', whiteSpace: 'pre-wrap', overflowWrap: 'anywhere'}}, r.body)},
      {key: 'product_name', label: 'Продукт', sortable: true, render: (r) => r.product_name ?? 'Изтрит продукт'},
      {key: 'rating', label: 'Оценка', sortable: true, sortValue: (r) => Number(r.rating ?? 0), render: (r) => r.rating ? r.rating + ' / 5' : '—'},
      {key: 'status', label: 'Статус', render: (r) => h('span', {className: 'react-status-badge status-' + (r.status === 'approved' ? 'active' : r.status === 'pending' ? 'draft' : 'inactive')}, states[r.status])},
      {key: 'created_at', label: 'Дата', sortable: true},
      {key: 'actions', label: 'Действия', render: (r) => h(C.TableActionsMenu, null,
        h(C.DropdownOption, {disabled: busy, onClick: () => edit(r)}, 'Преглед и отговор'),
        ...Object.entries(states).filter(([key]) => key !== r.status).map(([key, label]) => h(C.DropdownOption, {key, disabled: busy, onClick: () => action([r.id], key)}, key === 'approved' ? 'Одобри' : key === 'trash' ? 'Премести в кошчето' : key === 'spam' ? 'Отбележи като спам' : 'Върни за одобрение')))},
    ];
    return h('div', null,
      h('div', {className: 'react-page-heading'},
        h('div', null,
          h('h1', null, 'Коментари'),
          h(C.Breadcrumbs, {items: [{label: 'Коментари'}], onHomeClick: () => navigate?.('/')})),
        h(C.LoadingButton, {type: 'button', icon: h(icons.Settings), onClick: onOpenSettings}, 'Настройки на коментарите')),
      error && h('p', {role: 'alert'}, error),
      h(C.DataTable, {
        data: data?.records ?? [],
        columns,
        rowKey: (r) => r.id,
        loading: !data,
        emptyMessage: 'Няма коментари в този раздел.',
        pageSize: 25,
        selectable: !busy && !!data,
        selectedKeys: new Set(selected),
        onSelectionChange: (keys) => setSelected([...keys]),
        filterStorageKey: 'flex-reviews-comments',
        toolbar: h('div', {className: 'reviews-admin-filters'},
          h(Select, {label: 'Статус', value: status, options: {all: 'Всички', ...states}, onChange: (v) => { setStatus(v); setPage(1); }}),
          h('label', {className: 'react-form-field reviews-admin-search'}, 'Търсене',
            h(C.Input, {type: 'search', value: search, maxLength: 200, placeholder: 'Продукт, коментар или автор…', onChange: (e) => { setSearch(e.target.value); setPage(1); }}))),
        bulkActions: h(React.Fragment, null, h('strong', null, selected.length + ' избрани'), btn('Одобри', 'approved'), btn('Върни за одобрение', 'pending'), btn('Спам', 'spam'), btn('Премести в кошчето', 'trash')),
      }),
      data && data.pages > 1 && h('div', {className: 'reviews-admin-actions'},
        h(C.LoadingButton, {disabled: page <= 1, onClick: () => setPage(page - 1)}, 'Предишни'),
        h('span', null, page + ' / ' + data.pages),
        h(C.LoadingButton, {disabled: page >= data.pages, onClick: () => setPage(page + 1)}, 'Следващи')),
      editor && h(C.Dialog, {open: true, title: 'Коментар от ' + editor.name, onClose: () => setEditor(null)},
        h('p', null, editor.product_name),
        !conversation ? h('p', null, 'Зареждане на разговора…') : h(C.DataTable, {
          data: conversation.records,
          rowKey: (r) => r.id,
          pageSize: 25,
          selectable: !busy,
          selectedKeys: new Set(selected),
          onSelectionChange: (keys) => setSelected([...keys]),
          bulkActions: h(React.Fragment, null, btn('Одобри', 'approved'), btn('Спам', 'spam'), btn('В кошчето', 'trash')),
          columns: [
            {key: 'name', label: 'Автор', render: (r) => r.name + (r.is_store ? ' · От магазина' : '')},
            {key: 'body', label: 'Текст', render: (r) => h('span', {style: {whiteSpace: 'pre-wrap', overflowWrap: 'anywhere'}}, r.body)},
            {key: 'status', label: 'Статус', render: (r) => states[r.status]},
            {key: 'actions', label: 'Действия', render: (r) => h(C.TableActionsMenu, null, ...Object.entries(states).filter(([key]) => key !== r.status).map(([key, label]) => h(C.DropdownOption, {key, disabled: busy, onClick: () => action([r.id], key)}, label)))},
          ],
        }),
        conversation && conversation.pages > 1 && h('div', {className: 'reviews-admin-actions'},
          h(C.LoadingButton, {disabled: threadPage <= 1, onClick: () => setThreadPage(threadPage - 1)}, 'Предишни'),
          h('span', null, threadPage + ' / ' + conversation.pages),
          h(C.LoadingButton, {disabled: threadPage >= conversation.pages, onClick: () => setThreadPage(threadPage + 1)}, 'Следващи')),
        h('label', {className: 'react-form-field'}, 'Отговор от магазина', h(C.Textarea, {value: reply, maxLength: 3000, onChange: (e) => setReply(e.target.value)})),
        h('div', {className: 'reviews-admin-actions'},
          h(C.LoadingButton, {loading: busy, icon: h(icons.Save), onClick: () => action([editor.id], editor.status, reply)}, 'Публикувай отговор'),
          h(C.LoadingButton, {onClick: () => setEditor(null)}, 'Затвори'))));
  }

  return function ReviewsPage({navigate}) {
    const [view, setView] = React.useState('comments');
    return h(React.Fragment, null,
      h('link', {rel: 'stylesheet', href: '/extensions/flex/reviews/assets/assets/reviews.css'}),
      view === 'settings'
        ? h(SettingsPage, {navigate, onBack: () => setView('comments')})
        : h(CommentsPage, {navigate, onOpenSettings: () => setView('settings')}));
  };
}
