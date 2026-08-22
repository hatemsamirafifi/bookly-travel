import type { BlogPostCard } from '@/lib/api/types';
import BlogCard from './BlogCard';
import Pagination from '@/components/search/Pagination';

interface BlogListProps {
  posts: BlogPostCard[];
  locale: string;
  currentPage?: number;
  lastPage?: number;
}

export default function BlogList({
  posts,
  locale,
  currentPage,
  lastPage,
}: BlogListProps) {
  return (
    <div className="space-y-8">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {posts.map((post) => (
          <BlogCard key={post.id} post={post} locale={locale} />
        ))}
      </div>

      {currentPage !== undefined && lastPage !== undefined && lastPage > 1 && (
        <Pagination
          currentPage={currentPage}
          lastPage={lastPage}
          ariaLabel="Blog articles pagination"
        />
      )}
    </div>
  );
}
