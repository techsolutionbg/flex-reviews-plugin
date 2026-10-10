import assert from 'node:assert/strict';
import { createPage } from '../assets/admin.js';
let state = [];
const components = Object.fromEntries(['DataTable','TableActionsMenu','Breadcrumbs','Dialog','DropdownMenu','DropdownOption','DropdownChevron','CheckboxField','Input','Textarea','LoadingButton','CollapsibleSection'].map(name => [name, (props) => ({component:name,...props})]));
const React = {
  Fragment: 'fragment',
  useState: (initial) => [state.length ? state.shift() : initial, () => {}],
  useEffect: () => {},
  createElement(type, props, ...children) {
    assert.ok(type, 'Component must exist in the platform host API');
    return typeof type === 'function' ? type({...props, children}) : {type, props, children};
  }
};
const page = createPage({React,components,icons:{Settings:()=>null,Save:()=>null},request:()=>Promise.resolve({}),toast:()=>{}});
state = ['settings',{enabled:true,ratings:true,guests:true,moderation:true,trusted_authors:false,one_per_product:true,replies:true,per_page:10,sort:'newest',min_length:10,max_length:3000,interval_seconds:60,max_links:0,delete_on_uninstall:false,notify_admin:false,admin_email:'',notify_reply:false},false,''];
assert.ok(JSON.stringify(page({path:'/extension-pages/flex-reviews-settings'})).includes('Запази настройките'));
state = ['comments','pending','0',1,{records:[{id:1,name:'Автор',product_name:'Подарък',email:'a@example.invalid',created_at:'2026-10-08',rating:5,body:'Тестов текст за преглед',reply:null}],products:[{id:1,name:'Подарък'}],pages:1},[],false,'',0,null,''];
assert.ok(JSON.stringify(page({path:'/extension-pages/flex-reviews-comments'})).includes('Тестов текст за преглед'));
state = ['comments','pending','',1,{records:[],pages:1},[],false,'',0,{id:1,name:'Автор',product_name:'Подарък',status:'approved'},'',{records:[{id:1,name:'Автор',body:'Основен коментар',status:'approved'},{id:2,name:'Магазин',body:'Официален отговор',status:'approved',is_store:true}],pages:1},1];
assert.ok(JSON.stringify(page({path:'/extension-pages/flex-reviews-comments'})).includes('Официален отговор'));
console.log('Admin host integration: settings and comment list render successfully.');

