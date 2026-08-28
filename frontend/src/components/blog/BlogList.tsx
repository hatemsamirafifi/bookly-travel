import type { BlogPostCard } from '@/lib/api/types';
import BlogCard from './BlogCard';
import Pagination from '@/components/search/Pagination';

interface BlogListProps {
  posts: BlogPostCard[];
  locale: string;
  meta?: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  category?: string;
}

export default function BlogList({
  posts,
  locale,
  meta,
  category,
}: BlogListProps) {
  return (
    <div className="space-y-8">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {posts.map((post) => (
          <BlogCard key={post.id} post={post} locale={locale} />
        ))}
      </div>

      {meta && meta.last_page > 1 && (
        <Pagination
          currentPage={meta.current_page}
          lastPage={meta.last_page}
          ariaLabel="Blog articles pagination"
        />
      )}
    </div>
  );
}