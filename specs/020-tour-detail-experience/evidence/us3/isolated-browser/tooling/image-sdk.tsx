import React from 'react';
export default function Image({fill,src,alt,lang,sizes,loading,fetchPriority,className,onError}){return <img src={src} alt={alt} lang={lang} sizes={sizes} loading={loading} fetchPriority={fetchPriority} className={className} onError={onError} style={fill?{position:'absolute',inset:0,width:'100%',height:'100%'}:undefined}/>;}
