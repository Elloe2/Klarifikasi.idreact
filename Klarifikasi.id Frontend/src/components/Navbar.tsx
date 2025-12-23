'use client';

import Link from 'next/link';
import { motion } from 'framer-motion';
import { Search, Info, Menu } from 'lucide-react';
import { useState } from 'react';

export default function Navbar() {
    const [isOpen, setIsOpen] = useState(false);

    return (
        <nav className="fixed top-0 left-0 right-0 z-50 bg-glass transition-all duration-300">
            <div className="container mx-auto h-[var(--nav-height)] flex items-center justify-between">
                {/* Logo */}
                <Link href="/" className="flex items-center gap-2 group">
                    <motion.div
                        whileHover={{ rotate: 180 }}
                        transition={{ duration: 0.5, ease: "easeOut" }}
                    >
                        <div className="w-8 h-8 rounded-full bg-gradient-to-tr from-green-400 to-green-600 flex items-center justify-center">
                            <span className="text-black font-bold text-lg">K</span>
                        </div>
                    </motion.div>
                    <span className="text-xl font-bold tracking-tight">Klarifikasi<span className="text-accent">.id</span></span>
                </Link>

                {/* Desktop Links */}
                <div className="hidden md:flex items-center gap-8 text-sm font-medium text-secondary">
                    <NavLink href="/" icon={<Search size={18} />} text="Cek Fakta" />
                    <NavLink href="/about" icon={<Info size={18} />} text="Tentang" />

                    <motion.button
                        whileHover={{ scale: 1.05 }}
                        whileTap={{ scale: 0.95 }}
                        className="px-5 py-2 rounded-full bg-[#1DB954] text-black font-bold hover:bg-[#1ed760] transition-colors"
                    >
                        Login / Daftar
                    </motion.button>
                </div>

                {/* Mobile Toggle */}
                <button className="md:hidden text-white" onClick={() => setIsOpen(!isOpen)}>
                    <Menu />
                </button>
            </div>

            {/* Mobile Menu */}
            {isOpen && (
                <motion.div
                    initial={{ height: 0, opacity: 0 }}
                    animate={{ height: 'auto', opacity: 1 }}
                    exit={{ height: 0, opacity: 0 }}
                    className="md:hidden bg-card border-b border-white/5 overflow-hidden"
                >
                    <div className="flex flex-col p-4 gap-4">
                        <Link href="/" className="flex items-center gap-3 text-white">
                            <Search size={20} /> Cek Fakta
                        </Link>
                        <Link href="/about" className="flex items-center gap-3 text-white">
                            <Info size={20} /> Tentang
                        </Link>
                    </div>
                </motion.div>
            )}
        </nav>
    );
}

function NavLink({ href, icon, text }: { href: string; icon: React.ReactNode; text: string }) {
    return (
        <Link href={href} className="flex items-center gap-2 hover:text-white transition-colors relative group">
            {icon}
            <span>{text}</span>
            <span className="absolute -bottom-1 left-0 w-0 h-0.5 bg-green-500 transition-all group-hover:w-full" />
        </Link>
    );
}
