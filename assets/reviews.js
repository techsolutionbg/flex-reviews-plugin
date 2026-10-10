// Platform time module (0.1.59+): local time and "преди …". Without it the UTC date stays visible.
const flexTime = import('/assets/flex-time.js').catch(() => null);
function timeElement(value) {
  const raw = String(value || ''), node = document.createElement('time');
  node.textContent = raw.slice(0, 10).split('-').reverse().join('.');
  node.dateTime = raw.replace(' ', 'T') + (/(Z|[+-]\d{2}:?\d{2})$/i.test(raw) ? '' : 'Z');
  node.dataset.flexTime = 'date';
  flexTime.then((time) => time?.enhance(node));
  return node;
}
function initializeReviews() {
  document.querySelectorAll('[data-reviews-product]').forEach(root => {
    if (root.dataset.ready) return;
    root.dataset.ready = '1';
    const endpoint = '/plugins/flex/reviews/product/' + root.dataset.reviewsProduct;
    const form = root.querySelector('form'), status = root.querySelector('[data-reviews-status]');
    let token = '', busy = false, user = null;
    const text = (tag, value) => { const node = document.createElement(tag); node.textContent = value; return node; };
    const star = (filled) => {
      const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
      svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('aria-hidden', 'true');
      svg.classList.add('flex-review-star'); if (filled) svg.classList.add('is-filled');
      const path = document.createElementNS(svg.namespaceURI, 'path');
      path.setAttribute('d', 'm12 3 2.78 5.63L21 9.54l-4.5 4.39 1.06 6.2L12 17.2l-5.56 2.93 1.06-6.2L3 9.54l6.22-.91L12 3Z'); svg.append(path); return svg;
    };
    const picker = form.querySelector('.flex-rating-picker');
    if (picker) {
      const options = [...picker.querySelectorAll('.flex-rating-option')];
      const clear = picker.querySelector('[data-rating-clear]'), hint = picker.querySelector('[data-rating-hint]');
      const selected = () => Number(picker.querySelector('input:checked')?.value || 0);
      const descriptions = ['', 'Не съм доволен', 'Има какво да се подобри', 'Добър', 'Много добър', 'Отличен'];
      function paint(value = selected()) {
        options.forEach((option, index) => option.querySelector('svg').classList.toggle('is-filled', index < value));
        hint.textContent = value ? descriptions[value] + ' · ' + value + '/5' : 'Изберете от 1 до 5 звезди'; clear.hidden = !selected();
      }
      options.forEach((option, index) => {
        option.addEventListener('pointerenter', event => { if (event.pointerType !== 'touch') paint(index + 1); });
        option.addEventListener('focusin', () => paint(index + 1));
        option.addEventListener('change', () => paint());
      });
      picker.addEventListener('pointerleave', () => paint()); picker.addEventListener('focusout', () => paint());
      clear.addEventListener('click', () => { options.forEach(option => option.querySelector('input').checked = false); options[0].querySelector('input').focus(); paint(); });
      form.addEventListener('reset', () => queueMicrotask(() => paint())); paint();
    }
    function replyButton(id, name) {
      const b=text('button','Отговори'); b.type='button'; b.className='flex-reply-button'; b.dataset.replyTo=id; b.dataset.replyName=name; return b;
    }
    function answerCard(answer) {
      const article=text('article',''); article.id='flex-review-'+answer.id;article.tabIndex=-1; article.className='flex-review-answer'+(answer.is_store?' is-store':'');
      const header=text('header',''), avatar=text('span',Array.from(answer.name)[0]||'?'); avatar.className='flex-review-avatar'; avatar.setAttribute('aria-hidden','true');
      const author=text('div',''); author.className='flex-review-author'; const name=text('strong',answer.name);
      if(answer.is_store){const badge=text('span','От магазина');badge.className='flex-store-badge';name.append(badge);}
      author.append(name,timeElement(answer.created_at));header.append(avatar,author);article.append(header);
      if(answer.parent_name){const context=text('a','В отговор на '+answer.parent_name);context.className='flex-reply-context';context.href='#flex-review-'+answer.parent_id;context.dataset.reviewParent=answer.parent_id;article.append(context);}
      const body=text('p',answer.body);body.className='flex-review-body';article.append(body);
      if(root.dataset.userReplies==='1') article.append(replyButton(answer.id,answer.name));return article;
    }
    function thread(rootId, replies, count) {
      const wrapper=text('div','');wrapper.className='flex-review-thread';wrapper.dataset.thread=rootId;wrapper.dataset.threadPage='1';
      const list=text('div','');list.dataset.threadList='';replies.forEach(reply=>list.append(answerCard(reply)));wrapper.append(list);
      if(count>replies.length){const more=text('button','Покажи още отговори ('+(count-replies.length)+')');more.type='button';more.className='flex-reply-more';more.dataset.threadMore=rootId;wrapper.append(more);}return wrapper;
    }
    function identity(target) {
      if(user) for(const key of ['name','email']) {target.elements[key].value=user[key];target.elements[key].readOnly=true;}
    }
    root.addEventListener('click',async event=>{
      const parentLink=event.target.closest('[data-review-parent]');
      if(parentLink){
        const target=root.querySelector('#flex-review-'+Number(parentLink.dataset.reviewParent));
        if(target){event.preventDefault();target.focus({preventScroll:true});target.scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',block:'center'});target.classList.remove('is-reply-target');requestAnimationFrame(()=>target.classList.add('is-reply-target'));setTimeout(()=>target.classList.remove('is-reply-target'),2200);}
        return;
      }
      const more=event.target.closest('[data-thread-more]');
      if(more){more.disabled=true;try{const wrapper=more.closest('[data-thread]');const page=Number(wrapper.dataset.threadPage)+1;
        const response=await fetch('/plugins/flex/reviews/thread/'+more.dataset.threadMore+'?page='+page,{headers:{Accept:'application/json'}});
        const data=await response.json();if(!response.ok)throw new Error(data.error?.message||'Неуспешно зареждане.');
        data.records.forEach(answer=>wrapper.querySelector('[data-thread-list]').append(answerCard(answer)));wrapper.dataset.threadPage=data.page;
        if(data.page>=data.pages)more.remove();else more.textContent='Покажи още отговори';
      }catch(e){status.textContent=e.message;}finally{more.disabled=false;}return;}
      const button=event.target.closest('[data-reply-to]');if(!button)return;
      if(!user&&root.dataset.guestReplies!=='1'){status.textContent='Влезте в профила си, за да отговорите.';return;}
      root.querySelectorAll('[data-reply-form]').forEach(f=>f.remove());
      const target=form.cloneNode(true);target.dataset.replyForm='';target.querySelector('.flex-rating-picker')?.remove();target.querySelector('h3').textContent='Отговор до '+button.dataset.replyName;
      target.querySelector('[data-reviews-status]').textContent='';target.reset();identity(target);
      const parent=text('input','');parent.type='hidden';parent.name='parent_id';parent.value=button.dataset.replyTo;target.append(parent);
      const cancel=text('button','Отказ');cancel.type='button';cancel.className='flex-reply-button';cancel.addEventListener('click',()=>{target.remove();button.focus();});target.append(cancel);
      target.querySelector('[type=submit]').textContent='Изпрати отговор';button.after(target);bindForm(target);target.elements.body.focus();
    });
    async function load(page = 1, redraw = false) {
      const response = await fetch(endpoint + '?page=' + page, { headers: {Accept: 'application/json'}, credentials: 'same-origin' });
      if (!response.ok) throw new Error('Коментарите не могат да бъдат заредени.');
      const data = await response.json(); token = data.csrf_token; user=data.user; identity(form);
      if (redraw) {
        const list = root.querySelector('[data-reviews-list]'); list.replaceChildren();
        if (!data.records.length) list.append(text('p', 'Все още няма коментари.'));
        for (const review of data.records) {
          const article = text('article', ''); article.className = 'flex-review'; article.id='flex-review-'+review.id;article.tabIndex=-1;
          const head = text('header', '');
          const avatar = text('span', Array.from(review.name.trim())[0]?.toLocaleUpperCase('bg') || '?'); avatar.className = 'flex-review-avatar'; avatar.setAttribute('aria-hidden', 'true');
          const author = text('div', ''); author.className = 'flex-review-author';
          const time = timeElement(review.created_at);
          author.append(text('strong', review.name), time); head.append(avatar, author);
          if (review.rating) {
            const rating = text('div', ''); rating.className = 'flex-review-rating'; rating.setAttribute('role', 'img'); rating.setAttribute('aria-label', 'Оценка ' + review.rating + ' от 5');
            for (let i = 1; i <= 5; i++) rating.append(star(i <= review.rating));
            const value = text('span', review.rating); value.className = 'flex-review-rating-value'; value.append(text('small', '/5')); rating.append(value); head.append(rating);
          }
          const body = text('p', review.body); body.className = 'flex-review-body'; article.append(head, body);
          if(root.dataset.userReplies==='1') article.append(replyButton(review.id,review.name));
          if(root.dataset.replies==='1') article.append(thread(review.id,review.replies||[],review.reply_count||0));
          list.append(article);
        }
      }
      const tab = root.closest('[data-commerce-product-tabs]')?.querySelector('[data-commerce-tab=reviews]'); if (tab) tab.textContent = 'Коментари (' + data.count + ')';
      const pages = root.querySelector('[data-reviews-pagination]'); pages.replaceChildren();
      for (const [label, next] of [['Предишни', data.page - 1], ['Следващи', data.page + 1]]) {
        if (data.pages < 2) break;
        const button = text('button', label); button.type = 'button'; button.disabled = next < 1 || next > data.pages;
        button.addEventListener('click', () => load(next, true).catch(e => status.textContent = e.message)); pages.append(button);
      }
    }
    load().catch(e => status.textContent = e.message);
    function bindForm(targetForm) {
    targetForm.addEventListener('submit', async event => {
      event.preventDefault(); if (busy) return;
      busy = true; const button = targetForm.querySelector('[type=submit]'); const label=targetForm.dataset.replyForm!==undefined?'Изпрати отговор':'Изпрати коментар'; button.disabled = true; button.textContent = 'Изпращане…'; targetForm.querySelector('[data-reviews-status]').textContent = '';
      try {
        if (!token) await load();
        const fields = Object.fromEntries(new FormData(targetForm)); fields.notify_reply = fields.notify_reply === "on"; if(fields.parent_id) fields.parent_id=Number(fields.parent_id); fields.rating = fields.rating ? Number(fields.rating) : null;
        const response = await fetch(endpoint, {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': token}, body: JSON.stringify(fields)});
        const data = await response.json(); if (!response.ok) throw new Error(data.error?.message || 'Коментарът не беше изпратен.');
        targetForm.querySelector('[data-reviews-status]').textContent = data.message; targetForm.reset();identity(targetForm);
        if (data.result.status === 'approved') await load(1, true);
      } catch (e) { targetForm.querySelector('[data-reviews-status]').textContent = e.message; }
      finally { busy = false; button.disabled = false; button.textContent = label; }
    });
    }
    bindForm(form);
  });
}
initializeReviews();
document.addEventListener('flex:page-ready', initializeReviews);
document.addEventListener('commerce:navigated', initializeReviews);

