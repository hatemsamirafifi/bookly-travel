import Image from 'next/image';
import Link from 'next/link';
import type { BlogPostCard } from '@/lib/api/types';

interface BlogFeaturedHeroProps {
  post: BlogPostCard;
  locale: string;
}

export default function BlogFeaturedHero({ post, locale }: BlogFeaturedHeroProps) {
  return (
    <div className="relative overflow-hidden rounded-2xl bg-neutral-900 text-white shadow-xl mb-12">
      <div className="grid grid-cols-1 lg:grid-cols-12 min-h-[420px]">
        {/* Cover image */}
        <div className="relative lg:col-span-7 min-h-[280px] lg:min-h-[420px]">
          {post.cover_image ? (
            <Image
              src={post.cover_image}
              alt={post.title}
              fill
              className="object-cover"
              priority
              sizes="(max-width: 1024px) 100vw, 60vw"
            />
          ) : (
            <div className="absolute inset-0 bg-neutral-800 flex items-center justify-center text-neutral-500">
              <span>No image available</span>
            </div>
          )}
          <div className="absolute inset-0 bg-gradient-to-t from-neutral-900/90 via-neutral-900/30 to-transparent lg:bg-gradient-to-r lg:from-transparent lg:to-neutral-900" />
        </div>

        {/* Content */}
        <div className="lg:col-span-5 p-6 sm:p-8 lg:p-10 flex flex-col justify-between bg-neutral-900 z-10">
          <div>
            <div className="flex items-center gap-3 mb-4">
              <span className="inline-flex items-center rounded-full bg-primary-500/20 px-3 py-1 text-xs font-semibold text-primary-400 border border-primary-500/30">
                Featured Story
              </span>
              {post.primary_category && (
                <span className="text-xs text-neutral-400">
                  {post.primary_category.name}
                </span>
              )}
            </div>

            <Link href={`/${locale}/blog/${post.slug}`} className="group">
              <h2 className="text-2xl sm:text-3xl font-bold tracking-tight text-white group-hover:text-primary-400 transition-colors line-clamp-3">
                {post.title}
              </h2>
            </Link>

            {post.excerpt && (
              <p className="mt-4 text-sm text-neutral-300 line-clamp-3 leading-relaxed">
                {post.excerpt}
              </p>
            )}
          </div>

          <div className="mt-6 pt-6 border-t border-neutral-800 flex items-center justify-between">
            <div className="flex items-center gap-3">
              {post.author.avatar_url ? (
                <Image
                  src={post.author.avatar_url}
                  alt={post.author.name}
                  width={36}
                  height={36}
                  className="rounded-full object-cover"
                />
              ) : (
                <div className="w-9 h-9 rounded-full bg-neutral-700 flex items-center justify-center text-xs font-medium text-white">
                  {post.author.name.charAt(0).toUpperCase()}
                </div>
              )}
              <div>
                <p className="text-xs font-medium text-white">{post.author.name}</p>
                <p className="text-[11px] text-neutral-400">
                  {post.reading_time_minutes} min read
                </p>
              </div>
            </div>

            <Link
              href={`/${locale}/blog/${post.slug}`}
              className="inline-flex items-center text-sm font-semibold text-primary-400 hover:text-primary-300"
            >
              Read Article &rarr;
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}
