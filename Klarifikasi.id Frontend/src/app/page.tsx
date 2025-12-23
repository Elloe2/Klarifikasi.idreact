import Navbar from '@/components/Navbar';
import HeroSearch from '@/components/HeroSearch';

export default function Home() {
  return (
    <main className="min-h-screen bg-[var(--bg-primary)] text-white selection:bg-green-500 selection:text-black">
      <Navbar />
      <HeroSearch />

      {/* Footer Area Demo */}
      <footer className="text-center py-8 text-gray-600 text-sm">
        <p>© 2025 Klarifikasi.id - Memerangi Hoaks dengan Teknologi.</p>
      </footer>
    </main>
  );
}
