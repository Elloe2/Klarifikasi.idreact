'use client';

import { motion, AnimatePresence } from 'framer-motion';
import { Search, Sparkles, ArrowRight, Loader2, AlertCircle, CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import axios from 'axios';

export default function HeroSearch() {
    const [query, setQuery] = useState('');
    const [isFocused, setIsFocused] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [result, setResult] = useState<any>(null);
    const [error, setError] = useState('');
    const [activeTab, setActiveTab] = useState<'analysis' | 'results'>('analysis');

    const handleSearch = async () => {
        if (!query.trim()) return;

        setIsLoading(true);
        setError('');
        setResult(null);

        try {
            // Assuming backend is running locally on port 8000
            const response = await axios.post('http://127.0.0.1:8000/api/search', {
                query: query
            });

            setResult(response.data);
        } catch (err: any) {
            console.error(err);
            if (err.response) {
                setError(err.response.data?.message || `Server Error: ${err.response.status}`);
            } else if (err.request) {
                setError('Tidak dapat menghubungi server backend. Pastikan backend sudah jalan di port 8000.');
            } else {
                setError('Terjadi kesalahan yang tidak diketahui.');
            }
        } finally {
            setIsLoading(false);
        }
    };

    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter') handleSearch();
    };

    return (
        <div className="relative min-h-[80vh] flex flex-col items-center justify-center text-center px-4 overflow-hidden py-20">

            {/* Background Ambience */}
            <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-green-500/20 rounded-full blur-[120px] pointer-events-none" />

            <motion.div
                layout
                initial={{ opacity: 0, y: 30 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.8, ease: "easeOut" }}
                className="relative z-10 max-w-4xl w-full"
            >
                <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 border border-white/10 text-xs font-medium text-green-400 mb-6 backdrop-blur-sm">
                    <Sparkles size={12} />
                    <span>Powered by Gemini AI 2.0</span>
                </div>

                <h1 className="text-5xl md:text-7xl font-extrabold tracking-tight mb-6 leading-[1.1]">
                    Kebenaran di Ujung <br />
                    <span className="text-transparent bg-clip-text bg-gradient-to-r from-green-400 to-emerald-600">
                        Jari Anda
                    </span>
                </h1>

                <p className="text-lg md:text-xl text-gray-400 mb-10 max-w-2xl mx-auto leading-relaxed">
                    Platform fact-checking modern untuk memverifikasi berita, klaim, dan informasi viral dengan kecerdasan buatan.
                </p>

                {/* Search Bar */}
                <motion.div
                    layout
                    initial={{ scale: 0.9, opacity: 0 }}
                    animate={{ scale: 1, opacity: 1 }}
                    transition={{ delay: 0.2, duration: 0.5 }}
                    className={`
            relative w-full max-w-2xl mx-auto group z-20
            transition-all duration-300 ease-in-out
            ${isFocused ? 'scale-105' : 'scale-100'}
          `}
                >
                    <div className={`
            absolute -inset-1 rounded-2xl bg-gradient-to-r from-green-500 via-emerald-500 to-green-500 opacity-30 blur-md transition-opacity duration-300
            ${isFocused ? 'opacity-70' : 'opacity-30 group-hover:opacity-50'}
          `} />

                    <div className="relative flex items-center bg-[#181818] rounded-2xl border border-white/10 p-2 shadow-2xl">
                        <Search className="ml-4 text-gray-500" size={24} />
                        <input
                            type="text"
                            placeholder="Tempel link berita atau tulis klaim di sini..."
                            className="flex-1 bg-transparent border-none outline-none text-white px-4 py-3 text-lg placeholder:text-gray-600"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            onKeyDown={handleKeyDown}
                            onFocus={() => setIsFocused(true)}
                            onBlur={() => setIsFocused(false)}
                        />
                        <button
                            onClick={handleSearch}
                            disabled={isLoading}
                            className="bg-green-600 hover:bg-green-500 text-black font-bold p-3 rounded-xl transition-colors flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            {isLoading ? <Loader2 className="animate-spin" size={24} /> : <ArrowRight size={24} />}
                        </button>
                    </div>
                </motion.div>

                {/* Results Section */}
                <AnimatePresence>
                    {error && (
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            exit={{ opacity: 0 }}
                            className="mt-8 p-4 bg-red-500/10 border border-red-500/20 rounded-xl text-red-200 flex items-center gap-3 max-w-2xl mx-auto text-left"
                        >
                            <AlertCircle className="shrink-0" />
                            <div>{error}</div>
                        </motion.div>
                    )}

                    {result && (
                        <motion.div
                            initial={{ opacity: 0, y: 20 }}
                            animate={{ opacity: 1, y: 0 }}
                            className="mt-12 text-left max-w-3xl mx-auto space-y-6"
                        >
                            {/* TAB SWITCHER */}
                            <div className="flex gap-4 mb-8">
                                <button
                                    onClick={() => setActiveTab('analysis')}
                                    className={`flex-1 py-4 rounded-xl font-bold flex items-center justify-center gap-2 transition-all ${activeTab === 'analysis' ? 'bg-[#22c55e] text-black shadow-lg shadow-green-500/20' : 'bg-[#1e1e1e] text-gray-400 border border-white/5 hover:bg-[#252525]'}`}
                                >
                                    <Sparkles size={18} />
                                    Analisis AI
                                </button>
                                <button
                                    onClick={() => setActiveTab('results')}
                                    className={`flex-1 py-4 rounded-xl font-bold flex items-center justify-center gap-2 transition-all ${activeTab === 'results' ? 'bg-[#1e1e1e] text-white border border-white/10 shadow-lg' : 'bg-[#1e1e1e] text-gray-400 border border-white/5 hover:bg-[#252525]'}`}
                                >
                                    <Search size={18} />
                                    Hasil Pencarian
                                </button>
                            </div>

                            {activeTab === 'analysis' && result.gemini_analysis && (
                                <div className="bg-[#1e1e1e] border border-white/5 rounded-3xl p-6 md:p-8 shadow-2xl relative overflow-hidden">
                                    {/* AI HEADER */}
                                    <div className="flex items-center gap-4 mb-6">
                                        <div className="w-12 h-12 shrink-0 bg-gradient-to-br from-yellow-400 via-red-500 to-blue-600 rounded-xl p-[2px] rotate-45 flex items-center justify-center overflow-hidden">
                                            <div className="w-full h-full bg-[#1e1e1e] rounded-[10px] flex items-center justify-center -rotate-45">
                                                <Sparkles size={20} className="text-white fill-white/20" />
                                            </div>
                                        </div>
                                        <div className="flex flex-col">
                                            <h3 className="font-bold text-xl text-white">AI Fact-Checker</h3>
                                            <span className="text-xs text-gray-500 font-medium">Powered by Gemini AI</span>
                                        </div>
                                    </div>

                                    {/* VERDICT & CONFIDENCE BADGES */}
                                    <div className="flex flex-wrap gap-3 mb-8">
                                        {/* Dynamic Verdict Badge */}
                                        <div className={`flex items-center gap-2 px-4 py-2 rounded-xl border font-bold text-sm ${result.gemini_analysis.verdict === 'Tervalidasi' ? 'bg-green-500/10 border-green-500/30 text-green-400' :
                                            result.gemini_analysis.verdict === 'Terbantah' ? 'bg-red-500/10 border-red-500/30 text-red-400' :
                                                'bg-orange-500/10 border-orange-500/40 text-orange-400'
                                            }`}>
                                            <div className="w-5 h-5 rounded-full border-2 border-current flex items-center justify-center shrink-0">
                                                {result.gemini_analysis.verdict === 'Tervalidasi' ? <CheckCircle2 size={12} /> :
                                                    result.gemini_analysis.verdict === 'Terbantah' ? <AlertCircle size={12} /> :
                                                        <span className="text-[10px]">?</span>}
                                            </div>
                                            {result.gemini_analysis.verdict}
                                        </div>

                                        {/* Dynamic Confidence Badge */}
                                        <div className={`flex items-center gap-2 px-4 py-2 rounded-xl border font-bold text-sm ${result.gemini_analysis.confidence === 'Tinggi' ? 'bg-green-500/10 border-green-500/30 text-green-400' :
                                            result.gemini_analysis.confidence === 'Sedang' ? 'bg-orange-500/10 border-orange-500/40 text-orange-400' :
                                                'bg-red-500/10 border-red-500/40 text-red-400'
                                            }`}>
                                            <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" strokeWidth="3" fill="none" className="shrink-0"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>
                                            Confidence : {result.gemini_analysis.confidence}
                                        </div>
                                    </div>

                                    {/* EXPLANATION */}
                                    <div className="space-y-6">
                                        <div>
                                            <h4 className="text-white font-bold mb-3 text-base">Penjelasan:</h4>
                                            <p className="text-gray-300 leading-relaxed text-[15px] whitespace-pre-line">
                                                {result.gemini_analysis.explanation}
                                            </p>
                                        </div>

                                        <div>
                                            <h4 className="text-white font-bold mb-3 text-base">Analisis Mendalam:</h4>
                                            <div className="bg-green-500/5 border border-green-500/20 rounded-2xl p-5 text-gray-300 leading-relaxed text-[15px] whitespace-pre-line">
                                                {result.gemini_analysis.analysis.split('\n').map((line: string, i: number) => {
                                                    const trimmedLine = line.trim();
                                                    const isHeader = trimmedLine.startsWith('###');
                                                    const cleanLine = trimmedLine.replace(/^#+\s*/, '');
                                                    const parts = cleanLine.split(/(\*\*.*?\*\*)/);

                                                    return (
                                                        <div key={`line-${i}`} className={isHeader ? 'text-green-400 font-bold text-lg mt-4 mb-2' : 'min-h-[1.5em]'}>
                                                            {parts.map((part: string, j: number) => {
                                                                if (part.startsWith('**') && part.endsWith('**')) {
                                                                    return <strong key={`part-${j}`} className="text-white font-extrabold">{part.slice(2, -2)}</strong>;
                                                                }
                                                                return part;
                                                            })}
                                                        </div>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* SOURCE RESULTS TAB */}
                            {activeTab === 'results' && result.results && result.results.length > 0 && (
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between px-2">
                                        <h3 className="text-lg font-semibold text-gray-400">Hasil Pencarian</h3>
                                        <span className="text-xs font-mono text-gray-600 bg-white/5 px-2 py-1 rounded">
                                            {result.results.length} Sumber Ditemukan
                                        </span>
                                    </div>

                                    <div className="grid gap-4">
                                        {result.results.map((item: any, idx: number) => {
                                            // 1. Extract domain for logic
                                            const hostname = new URL(item.link).hostname.replace('www.', '');
                                            // 2. Mock 'Verified' status for major news outlets
                                            const isVerified = ['kompas.com', 'detik.com', 'cnnindonesia.com', 'tempo.co', 'cnbcindonesia.com', 'turnbackhoax.id', 'antaranews.com', 'tribunnews.com'].some(d => hostname.includes(d));

                                            // 3. Favicon as fallback thumbnail
                                            const faviconUrl = `https://www.google.com/s2/favicons?domain=${hostname}&sz=128`;

                                            return (
                                                <motion.div
                                                    key={idx}
                                                    initial={{ opacity: 0, y: 20 }}
                                                    animate={{ opacity: 1, y: 0 }}
                                                    transition={{ delay: 0.1 * idx }}
                                                    className="bg-[#232323] border border-white/10 rounded-2xl p-4 flex flex-col md:flex-row gap-4 text-left group hover:bg-[#2a2a2a] hover:border-green-500/30 transition-all shadow-lg hover:shadow-green-900/10 overflow-hidden relative"
                                                >

                                                    {/* LEFT: THUMBNAIL (Larger & Responsive) */}
                                                    <div className="shrink-0 w-full md:w-40 aspect-video md:aspect-square bg-white/5 rounded-xl overflow-hidden border border-white/5 relative self-start mt-1">
                                                        {item.thumbnail ? (
                                                            <img
                                                                src={item.thumbnail}
                                                                alt={hostname}
                                                                className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105"
                                                                onError={(e) => {
                                                                    // Fallback if image fails: hide image tag, show container background or fallback icon
                                                                    (e.target as HTMLImageElement).style.display = 'none';
                                                                }}
                                                            />
                                                        ) : (
                                                            // Fallback Placeholder if no thumbnail
                                                            <div className="w-full h-full flex items-center justify-center bg-gradient-to-br from-gray-800 to-gray-900">
                                                                <img src={faviconUrl} alt={hostname} className="w-8 h-8 opacity-50 grayscale" />
                                                            </div>
                                                        )}
                                                    </div>

                                                    {/* RIGHT: CONTENT AREA */}
                                                    <div className="flex-1 min-w-0 flex flex-col pt-0.5">

                                                        {/* HEADER: Branding (Favicon + Domain) */}
                                                        <div className="flex items-center gap-2 mb-2">
                                                            <img
                                                                src={faviconUrl}
                                                                alt={hostname}
                                                                className="w-5 h-5 rounded-full bg-white/10"
                                                                onError={(e) => (e.target as HTMLImageElement).style.display = 'none'}
                                                            />
                                                            <span className="text-sm font-bold text-blue-400 clamp-1 hover:underline cursor-pointer">
                                                                {hostname}
                                                            </span>
                                                            {isVerified && (
                                                                <CheckCircle2 size={14} className="text-blue-400 fill-blue-400/10" />
                                                            )}
                                                        </div>

                                                        {/* TITLE */}
                                                        <a href={item.link} target="_blank" rel="noopener noreferrer" className="block group-hover:text-green-400 transition-colors mb-2">
                                                            <h4 className="font-bold text-white text-base md:text-lg leading-snug line-clamp-2">
                                                                {item.title}
                                                            </h4>
                                                        </a>

                                                        {/* SNIPPET */}
                                                        <p className="text-sm text-gray-300 line-clamp-2 leading-relaxed font-light mb-4">
                                                            {item.snippet}
                                                        </p>

                                                        {/* FOOTER ACTIONS */}
                                                        <div className="flex items-center gap-3 mt-auto">

                                                            {/* Label Terverifikasi (Pill Style) */}
                                                            {isVerified && (
                                                                <div className="flex items-center gap-1 px-2 py-0.5 rounded border border-blue-500/30 bg-blue-500/10 text-blue-400 text-[10px] font-bold uppercase tracking-wider">
                                                                    VERIFIED
                                                                </div>
                                                            )}

                                                            <button
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    navigator.clipboard.writeText(item.link);
                                                                    const btn = e.currentTarget;
                                                                    const originalText = btn.innerHTML;
                                                                    btn.innerHTML = `<span class="text-green-400 flex items-center gap-1"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg> Disalin</span>`;
                                                                    setTimeout(() => btn.innerHTML = originalText, 2000);
                                                                }}
                                                                className="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/5 hover:bg-white/10 border border-white/10 text-gray-400 hover:text-white text-xs font-medium transition-colors cursor-pointer active:scale-95 ml-auto md:ml-0"
                                                            >
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="opacity-70"><rect width="14" height="14" x="8" y="8" rx="2" ry="2" /><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" /></svg>
                                                                <span>Salin Tautan</span>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </motion.div>
                                            )
                                        })}
                                    </div>
                                </div>
                            )}
                        </motion.div>
                    )}
                </AnimatePresence>

                {/* Quick Tags (Hidden when has result to reduce clutter) */}
                {!result && (
                    <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        transition={{ delay: 0.5 }}
                        className="mt-8 flex flex-wrap justify-center gap-3 text-sm text-gray-500"
                    >
                        <span>Trending:</span>
                        {['Pemilu 2024', 'Bansos', 'Ibu Kota Baru', 'Vaksin'].map((tag) => (
                            <button key={tag} className="hover:text-green-400 transition-colors underline decoration-dotted" onClick={() => { setQuery(tag); setTimeout(handleSearch, 100); }}>
                                #{tag}
                            </button>
                        ))}
                    </motion.div>
                )}

            </motion.div>
        </div>
    );
}
