const messages=MESSAGES;
export function useTranslations(namespace){return(key,values={})=>{const value=`${namespace}.${key}`.split('.').reduce((node,k)=>node?.[k],messages);if(typeof value!=='string')throw new Error(`Missing diagnostic catalog key ${namespace}.${key}`);return value.replace(/\{(\w+)\}/g,(_,k)=>String(values[k]));};}
