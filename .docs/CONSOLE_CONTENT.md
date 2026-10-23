# Console content

Description (archive intro, page 1 of `/roms/{slug}/`) and setup guide ("How to play" box on every rom page and download page) for each console term.
Generated from the database on 2026-10-23 21:24. Edit in WP Admin > Roms > Console, then regenerate.

| Console | Slug | Published roms | Badge | Description | Guide |
|---|---|---|---|---|---|
| PS2 | `playstation-2` | 5,452 | `#1E3A8A` | 123 words | 87 words |
| PS1 | `playstation` | 5,243 | `#94A3B8` | 127 words | 84 words |
| Nintendo DS | `nintendo-ds` | 5,048 | `#2DD4BF` | 119 words | 84 words |
| NES | `nes` | 3,154 | `#B91C1C` | 115 words | 78 words |
| Windows | `windows` | 3,118 | `#0078D4` | 141 words | 115 words |
| PSP | `playstation-portable` | 2,870 | `#0E7490` | 121 words | 76 words |
| PS3 | `ps3` | 2,807 | `#F472B6` | 116 words | 101 words |
| GBA | `game-boy-advance` | 2,708 | `#6366F1` | 126 words | 77 words |
| SNES | `super-nintendo` | 2,505 | `#7C3AED` | 122 words | 87 words |
| Wii | `nintendo-wii` | 2,497 | `#7DD3FC` | 107 words | 83 words |
| Xbox 360 | `xbox-360` | 2,391 | `#16A34A` | 105 words | 79 words |
| PS4 | `playstation-4` | 1,814 | `#2563EB` | 95 words | 63 words |
| Nintendo 3DS | `nintendo-3ds` | 1,693 | `#BE123C` | 104 words | 91 words |
| Sega Genesis | `sega-genesis` | 1,349 | `#F87171` | 117 words | 87 words |
| Xbox | `xbox` | 1,205 | `#107C10` | 99 words | 92 words |
| PS Vita | `ps-vita` | 1,202 | `#0EA5E9` | 100 words | 87 words |
| Sega Saturn | `sega-saturn` | 1,183 | `#34D399` | 118 words | 74 words |
| Game Boy | `game-boy` | 1,157 | `#A3E635` | 114 words | 77 words |
| Game Boy Color | `game-boy-color` | 1,138 | `#F59E0B` | 108 words | 86 words |
| GameCube | `gamecube` | 875 | `#4C1D95` | 91 words | 87 words |
| Nintendo 64 | `nintendo-64` | 871 | `#CA8A04` | 111 words | 88 words |
| Wii U | `wii-u` | 842 | `#0891B2` | 104 words | 66 words |
| Sega Dreamcast | `dreamcast` | 711 | `#F97316` | 136 words | 100 words |
| TeknoParrot | `teknoparrot` | 399 | `#C026D3` | 140 words | 83 words |
| Sega CD | `sega-cd` | 230 | `#0F766E` | 135 words | 93 words |
| Nintendo Virtual Boy | `nintendo-virtual-boy` | 5 | `#7F1D1D` | 130 words | 95 words |
| Nintendo Pokemon Mini | `nintendo-pokemon-mini` | 5 | `#FACC15` | 131 words | 80 words |
| Nintendo Switch | `switch` | 0 | `#E60012` | 135 words | 94 words |
| Unknown | `unknown` | 0 | `-` | — | — |

---

## PS2

`/roms/playstation-2/` · 5,452 published roms

### Description

PS2 ROMs come from the best-selling video game console of all time. Sony released the PlayStation 2 in 2000, and by the time production ended in 2013 it had sold around 160 million units. It played DVDs, ran most PS1 games, and stayed on store shelves for more than a decade.

The library is huge and covers every genre. Grand Theft Auto: San Andreas, Final Fantasy X, Metal Gear Solid 3, Shadow of the Colossus, God of War, Kingdom Hearts, Gran Turismo 4 and Okami are only a start, and many lesser-known Japanese games have fan translations.

PS2 games on ROMSVAULT are Redump disc images, offered as ISO or CHD. PCSX2 runs them on PC with upscaling well beyond the original resolution, and NetherSX2 brings the library to Android.

### Setup guide

PS2 games here are Redump disc images in two formats: **ISO**, packed in a 7z archive to save space, and **CHD**, which is already compressed and needs no unpacking.

- **PC (Windows, macOS, Linux)**: [PCSX2](/emulators/playstation-2/pcsx2/).
- **Android**: NetherSX2, based on [AetherSX2](/emulators/playstation-2/aethersx2/).

Extract the 7z to get the `.iso`, or open the `.chd` as it is; current PCSX2 releases read both. PCSX2 needs a PS2 BIOS dumped from a console, placed in its `bios` folder. Start at 2x or 3x internal resolution and raise it if your GPU keeps up.

---

## PS1

`/roms/playstation/` · 5,243 published roms

### Description

PS1 ROMs cover the original Sony PlayStation, launched in 1994 in Japan and in 1995 in the West. It was the first console to sell more than 100 million units, and its move to CD-ROM made room for 3D worlds, voice acting and long cutscenes.

Many of the most loved games of the 1990s started here: Final Fantasy VII, Metal Gear Solid, Resident Evil 2, Castlevania: Symphony of the Night, Crash Bandicoot, Spyro the Dragon, Gran Turismo and Tekken 3. The library also has a long tail of Japanese RPGs and oddities, many now in English through fan translations.

PS1 games on ROMSVAULT are Redump BIN/CUE images. DuckStation is the easiest way to play them on PC and Android, with widescreen hacks and sharp upscaling that the original hardware never had.

### Setup guide

PS1 games here are Redump disc images: a `.cue` file plus one or more `.bin` tracks.

- **PC (Windows, macOS, Linux)**: [DuckStation](/emulators/playstation/duckstation/), or [ePSXe](/emulators/playstation/epsxe/) on Windows.
- **Android**: DuckStation or ePSXe.

Extract the download if it is compressed and keep the `.cue` and `.bin` files together, then open the `.cue`. DuckStation needs a PS1 BIOS such as SCPH-1001 or SCPH-5501, placed in its `bios` folder. For games on several discs, create an `.m3u` playlist listing each `.cue` so you can swap discs from the menu.

---

## Nintendo DS

`/roms/nintendo-ds/` · 5,048 published roms

### Description

Nintendo DS ROMs come from Nintendo's dual-screen handheld, released in 2004. The DS had a touch screen on the bottom, a microphone and built-in wireless, and it sold about 154 million units, which makes it Nintendo's best-selling handheld.

The touch screen led to games that were hard to make anywhere else: Brain Age, Nintendogs, Professor Layton, Phoenix Wright and The World Ends with You. Alongside them sit New Super Mario Bros., Mario Kart DS and the Pokémon games from Diamond and Pearl to Black 2 and White 2.

DS games are .nds files and run well on almost any device. melonDS and NooDS work on PC and Android, DraStic is a fast option on Android, and Delta plays DS games on iPhone.

### Setup guide

Nintendo DS games are `.nds` files. A smaller group marked *DSi Decrypted* are DSi-enhanced or DSiWare titles.

- **PC (Windows, macOS, Linux)**: [melonDS](/emulators/nintendo-ds/melonds/), or [NooDS](/emulators/nintendo-ds/noods/).
- **Android**: melonDS, NooDS or DraStic.
- **iPhone and iPad**: [Delta](/emulators/game-boy/delta-emulator/).

Open the `.nds` file and the game boots; regular DS games do not need a BIOS. The touch screen is controlled with the mouse on PC and with your finger on a phone. DSi titles have to run in DSi mode in melonDS, which needs the DSi BIOS and NAND files.

---

## NES

`/roms/nes/` · 3,154 published roms

### Description

NES ROMs cover the Nintendo Entertainment System, known as the Famicom in Japan. It launched in Japan in 1983 and in North America in 1985, sold about 62 million units and helped bring home video games back after the 1983 crash.

Many long-running series began on this console. Super Mario Bros., The Legend of Zelda, Metroid, Mega Man, Castlevania, Final Fantasy and Dragon Quest all started on the NES, next to classics like Contra, Punch-Out!! and DuckTales. Hacks and fan translations add many more games, including Japan-only Famicom titles.

NES games on ROMSVAULT are No-Intro .nes files. Mesen is the most accurate emulator on PC, and NES games run on nearly anything, from old phones to a web browser.

### Setup guide

NES games here are No-Intro `.nes` files, so each one matches the original cartridge.

- **PC (Windows, macOS, Linux)**: [Mesen](/emulators/game-boy/mesen/), FCEUX or Nestopia.
- **Android**: NES.emu or RetroArch with the Mesen or FCEUmm core.
- **iPhone and iPad**: [Delta](/emulators/game-boy/delta-emulator/).

Unzip the download if needed and open the `.nes` file. No BIOS is required. Hacks and fan translations load the same way as any other NES game. For the closest look to a CRT, turn on the NTSC filter in Mesen.

---

## Windows

`/roms/windows/` · 3,118 published roms

### Description

Windows ROMs on ROMSVAULT are full PC games rather than console dumps. Most come as GOG offline installers, which are DRM-free and install like any normal Windows program. Older retail titles are packed by MagiPack, a group that prepares classic PC games to run on modern versions of Windows. You will also find fan games built with tools like RPG Maker and GameMaker.

The library leans toward the 1990s and 2000s: point-and-click adventures, real-time strategy, early 3D shooters and RPGs that are hard to buy today. Each page lists the version, the file size and the language, so you can check that a game fits your setup before downloading.

There is no emulator to set up. Download, unpack, run the installer and play. Games that predate Windows XP sometimes need compatibility mode or a wrapper such as dgVoodoo2 for the graphics.

### Setup guide

Windows games here come in three forms, and the first word of the file type tells you which one you have.

- **GOG**: an offline installer (`setup_*.exe`, sometimes with extra `.bin` parts). Keep all parts in one folder and run the `.exe`.
- **MagiPack**: a repack of an older retail game. Run the installer; it sets the game up for Windows 10 and 11.
- **Fan game**: usually a `.zip`. Extract it and start the game `.exe` inside.

If an old game shows a black screen or runs too fast, try right-click > Properties > Compatibility, or a graphics wrapper like dgVoodoo2. On Linux and Steam Deck, most of these installers work through Proton, Lutris or the Heroic launcher.

---

## PSP

`/roms/playstation-portable/` · 2,870 published roms

### Description

PSP ROMs come from the PlayStation Portable, Sony's first handheld. It launched in Japan in 2004 and in the West in 2005, sold around 80 million units, and put console-style graphics in a pocket device with games on small UMD discs.

The library is full of games built for the hardware rather than cut-down ports. God of War: Chains of Olympus, Crisis Core: Final Fantasy VII, Monster Hunter Freedom Unite, Persona 3 Portable, Metal Gear Solid: Peace Walker, Patapon and LocoRoco are some of the best, and Japan got many more that fans have since translated.

PSP games here come as Redump and No-Intro dumps, PSN releases and CHD images. PPSSPP plays them on PC, Android and iPhone, usually in HD and at full speed.

### Setup guide

PSP games here come in several sets: **Redump** and **No-Intro** UMD dumps in a zip, **PSN** digital releases, and **CHD** images.

- **PC, Android, iPhone and iPad**: [PPSSPP](/emulators/playstation-portable/ppsspp/).

Unzip the download; depending on the game, you get an `.iso`, or a `.cue` with `.bin` files. Open it in PPSSPP. CHD files load directly without extracting. No BIOS is needed. In PPSSPP, set the rendering resolution to 2x or higher for a sharp picture on a modern screen.

---

## PS3

`/roms/ps3/` · 2,807 published roms

### Description

PS3 ROMs come from the PlayStation 3, released by Sony in 2006. It was the first console with a Blu-ray drive, ran on the unusual Cell processor, and sold about 87 million units over its life.

Its exclusives still hold up: The Last of Us, Uncharted 2, Demon's Souls, Metal Gear Solid 4, God of War III, LittleBigPlanet and Ni no Kuni. The PSN store also had a large catalog of digital games and PS1 classics, many of which are listed here.

PS3 games on ROMSVAULT are mostly decrypted disc ISOs and PSN packages. RPCS3 runs a large part of the library on PC, but it needs a strong CPU, so check its compatibility list before you download a big game.

### Setup guide

PS3 games here come in two main forms: **ISO (Decrypted)** disc images and **PSN** games as `.pkg` files.

- **PC (Windows, Linux, macOS)**: [RPCS3](/emulators/ps3/rpcs3/).
- **Android**: [aPS3e](/emulators/ps3/aps3e/) or [RPCSX UI](/emulators/ps3/rpcsx-ui/), for high-end phones only.

Install the PS3 firmware in RPCS3 first (File > Install Firmware). For a disc game, extract the decrypted ISO into a folder and add it with File > Add Games. For a PSN game, use File > Install Packages and select the `.pkg` together with its `.rap` license file. Encrypted Redump ISOs have to be decrypted first with a tool like PS3Dec and the matching [disc key](https://ia801604.us.archive.org/view_archive.php?archive=/33/items/sony-play-station-3-disc-keys-txt/Sony%20-%20PlayStation%203%20-%20Disc%20Keys%20TXT.zip).

---

## GBA

`/roms/game-boy-advance/` · 2,708 published roms

### Description

GBA ROMs come from the Game Boy Advance, Nintendo's 32-bit handheld released in 2001. It sold about 81 million units across the original model, the SP and the Micro, and it could still play older Game Boy and Game Boy Color cartridges.

The GBA was home to some of the best 2D games ever made: Pokémon Ruby, Sapphire, Emerald and FireRed, Metroid Fusion and Zero Mission, Golden Sun, Advance Wars, Fire Emblem, The Minish Cap and Castlevania: Aria of Sorrow. Mother 3 never left Japan, but its fan translation is one of the most played on the system.

GBA games here are No-Intro .gba files. mGBA is the reference emulator on PC, while My Boy! and Pizza Boy A Pro are popular on Android and Delta covers iPhone.

### Setup guide

GBA games here are No-Intro `.gba` files.

- **PC (Windows, macOS, Linux)**: mGBA, or [VisualBoyAdvance](/emulators/game-boy-advance/visualboyadvance/).
- **Android**: [My Boy!](/emulators/game-boy-advance/my-boy/), [Pizza Boy A Pro](/emulators/game-boy-advance/pizza-boy-a-pro/) or mGBA.
- **iPhone and iPad**: [Delta](/emulators/game-boy/delta-emulator/).

Open the `.gba` file and play. A BIOS is optional because these emulators include a replacement, but the original `gba_bios.bin` fixes the few games that check for it and shows the boot logo. Saves are written next to the game file, so keep both in the same folder.

---

## SNES

`/roms/super-nintendo/` · 2,505 published roms

### Description

SNES ROMs cover the Super Nintendo Entertainment System, called the Super Famicom in Japan. It launched in 1990 in Japan and 1991 in North America and sold about 49 million units. Its Mode 7 scaling and rich sound chip set it apart from other 16-bit consoles.

For many players this is the best library of the 16-bit era. Super Mario World, The Legend of Zelda: A Link to the Past, Super Metroid, Chrono Trigger, Final Fantasy VI, Donkey Kong Country, EarthBound and Super Mario RPG are all here, plus a large set of Super Famicom games in English through fan translations.

SNES games on ROMSVAULT are No-Intro .sfc files. Snes9x runs everywhere, bsnes and Mesen aim for exact accuracy, and Delta plays them on iPhone.

### Setup guide

SNES games here are mostly No-Intro `.sfc` files, with the same layout as the original cartridges.

- **PC (Windows, macOS, Linux)**: Snes9x for speed, bsnes for accuracy, or [Mesen](/emulators/game-boy/mesen/).
- **Android**: Snes9x EX+ or RetroArch with the Snes9x core.
- **iPhone and iPad**: [Delta](/emulators/game-boy/delta-emulator/).

Open the `.sfc` or `.smc` file; no BIOS is needed. Games with extra chips, such as Star Fox (Super FX) or Super Mario RPG (SA-1), run fine in current versions of these emulators. Hacks and fan translations load like any other SNES game.

---

## Wii

`/roms/nintendo-wii/` · 2,497 published roms

### Description

Wii ROMs come from the Nintendo Wii, released in 2006. Its motion-sensing Wii Remote brought in people who had never played video games, and the console sold more than 100 million units.

Wii Sports made it famous, but the library goes much further: Super Mario Galaxy 1 and 2, Mario Kart Wii, Super Smash Bros. Brawl, The Legend of Zelda: Twilight Princess and Skyward Sword, Metroid Prime 3, Xenoblade Chronicles and Donkey Kong Country Returns. WiiWare and Virtual Console added hundreds of smaller and older games.

Wii disc games on ROMSVAULT are in RVZ format and WiiWare titles come as WAD files. Dolphin plays both on PC and Android, often in HD.

### Setup guide

Wii disc games here are in **RVZ**, Dolphin’s compressed disc format. WiiWare and Virtual Console titles come as **WAD** files.

- **PC (Windows, macOS, Linux) and Android**: [Dolphin](/emulators/gamecube/dolphin-emulator/).

Add the folder with your `.rvz` files to Dolphin’s game list and double-click a game to start it. For WAD files, use Tools > Install WAD; the title then appears in the game list. Motion controls can be mapped to a mouse or gamepad, or you can pair a real Wii Remote over Bluetooth.

---

## Xbox 360

`/roms/xbox-360/` · 2,391 published roms

### Description

Xbox 360 ROMs come from Microsoft's second console, launched in 2005. It sold about 84 million units and made online play normal on consoles, with Xbox Live, achievements and a big store of download-only Xbox Live Arcade games.

Halo 3, Gears of War, Mass Effect, Fable II, Forza Motorsport 4 and Left 4 Dead 2 defined the console, and multiplatform hits like Red Dead Redemption and Skyrim found a large audience there. XBLA added indie favorites such as Braid, Limbo and Castle Crashers.

Xbox 360 games here are Redump disc ISOs and No-Intro XBLA packages. Xenia runs a growing share of them on Windows; check its compatibility list for the game you want.

### Setup guide

Xbox 360 disc games here are Redump **ISO** images. Xbox Live Arcade titles are No-Intro **XBLA** packages.

- **PC (Windows)**: Xenia Canary. There is no stable Xbox 360 emulator for Android or iOS.

Extract the download, then use File > Open in Xenia and select the `.iso`, or the package file of an XBLA game. Compatibility varies a lot from game to game, so check the Xenia compatibility list first. A recent GPU with Vulkan or DirectX 12 support gives the best results.

---

## PS4

`/roms/playstation-4/` · 1,814 published roms

### Description

PS4 ROMs come from the PlayStation 4, released by Sony in 2013. It sold more than 117 million units and became one of the most successful consoles Sony ever made.

Its exclusives include Bloodborne, Uncharted 4, The Last of Us Part II, God of War, Marvel's Spider-Man, Horizon Zero Dawn and Ghost of Tsushima, along with Japanese games like Persona 5 and Yakuza 0.

PS4 emulation is still at an early stage. shadPS4 can already run some games on PC, Bloodborne among them, but many titles do not boot yet. Check the shadPS4 compatibility list before downloading a large PS4 game.

### Setup guide

PS4 games are installed from **PKG** files.

- **PC (Windows, Linux, macOS)**: [shadPS4](/emulators/playstation-4/shadps4/).

In shadPS4, use File > Install Packages and select the `.pkg`. Install the base game first, then any update PKG. PS4 emulation is still young: some games run well, many do not boot yet. Check the shadPS4 compatibility list before downloading a large game, and use a recent CPU and GPU.

---

## Nintendo 3DS

`/roms/nintendo-3ds/` · 1,693 published roms

### Description

Nintendo 3DS ROMs come from the handheld that showed 3D images without glasses. Nintendo released it in 2011, and across the 3DS, 2DS and New 3DS models it sold about 76 million units.

It is one of Nintendo's richest libraries: Pokémon X, Y, Sun and Moon, Animal Crossing: New Leaf, Fire Emblem Awakening, The Legend of Zelda: A Link Between Worlds, Monster Hunter 4 Ultimate, Super Mario 3D Land and Kid Icarus: Uprising, plus remakes of Ocarina of Time and Majora's Mask.

3DS games on ROMSVAULT are No-Intro decrypted dumps, eShop titles and CIA files. Azahar, the successor to Citra, plays them on PC and Android.

### Setup guide

3DS games here come in a few formats. **Decrypted** No-Intro dumps (`.3ds`) and **eShop** titles run in emulators. **CIA** files can also be installed on a real 3DS with custom firmware.

- **PC (Windows, macOS, Linux) and Android**: [Azahar](/emulators/nintendo-3ds/azahar-emulator/), the successor to Citra.

Extract the archive; the default password for zip and 7z files is **romsfun-romspure**. Open a decrypted `.3ds` file directly in Azahar. For a CIA file, use File > Install CIA and the game appears in the list. Updates and DLC are CIA files too and install the same way.

---

## Sega Genesis

`/roms/sega-genesis/` · 1,349 published roms

### Description

Sega Genesis ROMs cover Sega's 16-bit console, sold as the Mega Drive outside North America. It launched in Japan in 1988 and in North America in 1989, and it gave Nintendo real competition for the first time, selling over 30 million units.

Sonic the Hedgehog 1, 2 and 3, Streets of Rage 2, Gunstar Heroes, Phantasy Star IV, Shining Force, Ecco the Dolphin and Castlevania: Bloodlines show its range. Sports games and arcade ports were a big part of its appeal, and Mortal Kombat on Genesis kept the blood the SNES version cut.

Genesis games here are cartridge dumps, along with hacks and fan translations. Genesis Plus GX and BlastEm play them with near-perfect accuracy on PC, and every Android emulator frontend supports them.

### Setup guide

Sega Genesis games here are cartridge dumps, usually `.md` or `.bin` files.

- **PC (Windows, macOS, Linux)**: Genesis Plus GX in RetroArch, BlastEm, or Kega Fusion on Windows.
- **Android**: MD.emu, or RetroArch with Genesis Plus GX or PicoDrive.

Open the file and play; Genesis games do not need a BIOS. Most games use the 3-button pad, and some, like Street Fighter II, use the 6-button layout, which you can turn on in the emulator’s input settings. Hacks and fan translations load like any other Genesis game.

---

## Xbox

`/roms/xbox/` · 1,205 published roms

### Description

Xbox ROMs come from Microsoft's first console, launched in 2001. The original Xbox had a built-in hard drive and PC-like hardware, sold about 24 million units, and set up Xbox Live for online play in 2002.

Halo: Combat Evolved and Halo 2 carried the console, joined by Star Wars: Knights of the Old Republic, Fable, Ninja Gaiden, Jet Set Radio Future, Splinter Cell and Panzer Dragoon Orta. Many of these were never ported, which makes the original Xbox worth emulating.

Xbox games on ROMSVAULT are mostly in XISO format, which xemu loads directly. xemu runs a large part of the library on PC.

### Setup guide

Xbox games here are mostly **XISO** images, the format [xemu](/emulators/xbox/xemu-emulator/) reads without any conversion. Redump ISOs are also listed, but they are meant for preservation; use the XISO version to play.

- **PC (Windows, macOS, Linux)**: [xemu](/emulators/xbox/xemu-emulator/).

xemu needs three system files before it starts: the MCPX boot ROM, a flash BIOS and a hard disk image. Set them in Machine > Settings > System, then load the `.iso` with Machine > Load Disc. Some games need a few tries to boot on the first run while the hard disk image is prepared.

---

## PS Vita

`/roms/ps-vita/` · 1,202 published roms

### Description

PS Vita ROMs come from Sony's last handheld, released in 2011 in Japan and early 2012 in the West. The Vita had an OLED screen, two analog sticks, and touch panels on the front and back.

It never sold as well as the PSP, but its library has a loyal following: Persona 4 Golden, Gravity Rush, Uncharted: Golden Abyss, Tearaway, Danganronpa, Killzone Mercenary and many visual novels. It also became a great place for indie games like Hotline Miami and Spelunky.

Vita games here come as NoNpDrm, PSV and VPK dumps. Vita3K plays a growing number of them on PC and Android.

### Setup guide

PS Vita games here come in a few dump formats: **NoNpDrm**, **PSV** (PSN titles), **VPK** and MaiDump.

- **PC (Windows, Linux, macOS) and Android**: [Vita3K](/emulators/ps-vita/vita3k/).

In Vita3K, first install the PS Vita firmware from the setup screen. Then use File > Install .zip, .vpk and choose the game. NoNpDrm dumps need their license file (`work.bin`); Vita3K asks for it during install. On a modded PS Vita, install VPK files with VitaShell. Compatibility is still growing, so check the Vita3K list for your game.

---

## Sega Saturn

`/roms/sega-saturn/` · 1,183 published roms

### Description

Sega Saturn ROMs come from Sega's 32-bit console, launched in Japan in 1994 and in the West in 1995. It used two CPUs and was hard to program for. It sold about 9 million units, most of them in Japan, where it outsold the Nintendo 64.

The Saturn is known for 2D games and arcade ports: Guardian Heroes, NiGHTS into Dreams, Panzer Dragoon Saga, Virtua Fighter 2, Sega Rally Championship, Shining Force III and Radiant Silvergun. Much of its best work stayed in Japan, and fan translations have opened many of those games up.

Saturn games here are disc images, many in CHD format. Beetle Saturn in RetroArch is the most accurate emulator on PC, while YabaSanshiro 2 is the usual choice on Android.

### Setup guide

Sega Saturn games here are disc images, many in **CHD** format.

- **PC (Windows, macOS, Linux)**: Beetle Saturn in RetroArch, or Kronos.
- **Android**: [YabaSanshiro 2](/emulators/sega-saturn/yabasanshiro-2/), or RetroArch with the Yabause core.

The Saturn needs a BIOS. For Beetle Saturn, put `sega_101.bin` (Japan) and `mpr-17933.bin` (USA and Europe) in the RetroArch `system` folder. Then load the `.chd` or `.cue` file. Saturn emulation is demanding; if a game stutters, try YabaSanshiro or lower the internal resolution.

---

## Game Boy

`/roms/game-boy/` · 1,157 published roms

### Description

Game Boy ROMs come from Nintendo's first big handheld, released in 1989. It had a green monochrome screen and ran for hours on four AA batteries. Together with the Game Boy Color, it sold close to 119 million units.

Tetris came with the console in the West and became one of the best-selling games ever. Pokémon Red and Blue started a worldwide craze, and The Legend of Zelda: Link's Awakening, Super Mario Land 2, Kirby's Dream Land and Metroid II are still worth playing today.

Game Boy games here are No-Intro .gb files, plus hacks and fan translations. mGBA, SameBoy and Mesen play them on PC, Pizza Boy C Pro on Android and Delta on iPhone.

### Setup guide

Game Boy games here are No-Intro `.gb` files.

- **PC (Windows, macOS, Linux)**: mGBA, SameBoy, [Mesen](/emulators/game-boy/mesen/) or [SkyEmu](/emulators/game-boy/skyemu/).
- **Android**: [Pizza Boy C Pro](/emulators/game-boy/pizza-boy-c-pro/), or RetroArch with the Gambatte core.
- **iPhone and iPad**: [Delta](/emulators/game-boy/delta-emulator/).

Open the `.gb` file; no BIOS is needed. Most emulators let you pick a color palette, from the original green screen to the colors a Game Boy Color or Super Game Boy would add. Hacks and fan translations load like any other Game Boy game.

---

## Game Boy Color

`/roms/game-boy-color/` · 1,138 published roms

### Description

Game Boy Color ROMs come from the color successor to the Game Boy, released in 1998. It added a color screen and a faster CPU, and it could still play the whole original Game Boy library.

Pokémon Gold, Silver and Crystal are its biggest games, together with The Legend of Zelda: Oracle of Seasons and Oracle of Ages, Link's Awakening DX, Wario Land 3, Shantae, Dragon Warrior Monsters and a surprisingly good Metal Gear Solid.

GBC games on ROMSVAULT are .gbc files, along with hacks and fan translations. The same emulators that run Game Boy games handle them, from mGBA on PC to Pizza Boy C Pro on Android.

### Setup guide

Game Boy Color games are `.gbc` files.

- **PC (Windows, macOS, Linux)**: mGBA, SameBoy, [Mesen](/emulators/game-boy/mesen/) or [SkyEmu](/emulators/game-boy/skyemu/).
- **Android**: [Pizza Boy C Pro](/emulators/game-boy/pizza-boy-c-pro/), or RetroArch with the Gambatte core.
- **iPhone and iPad**: [Delta](/emulators/game-boy/delta-emulator/).

Open the `.gbc` file and play; no BIOS is required. Some GBC games have a real-time clock (Pokémon Gold, Silver and Crystal, for example). Emulators keep that clock in the save file, so leave the save next to the game. Turn on color correction if the colors look too bright on a modern screen.

---

## GameCube

`/roms/gamecube/` · 875 published roms

### Description

GameCube ROMs come from Nintendo's compact console released in 2001. It used small 8 cm discs and a controller that many players still prefer, and it sold about 22 million units.

What it lacked in sales it made up for in quality: Super Smash Bros. Melee, The Legend of Zelda: The Wind Waker, Metroid Prime, Super Mario Sunshine, Pikmin, Paper Mario: The Thousand-Year Door, F-Zero GX, Eternal Darkness and Resident Evil 4 all started here.

GameCube games here are in RVZ format. Dolphin plays them on PC and Android, often in HD with widescreen patches.

### Setup guide

GameCube games here are in **RVZ**, Dolphin’s compressed disc format. It saves space without losing any data.

- **PC (Windows, macOS, Linux) and Android**: [Dolphin](/emulators/gamecube/dolphin-emulator/).

Point Dolphin at the folder with your `.rvz` files and double-click a game. No BIOS is needed. Set up a controller under Controllers > Standard Controller; a modern gamepad works fine, and a GameCube adapter lets you use original controllers. For games on two discs, keep both discs in the same folder and turn on Auto Disc Change in Dolphin’s settings.

---

## Nintendo 64

`/roms/nintendo-64/` · 871 published roms

### Description

Nintendo 64 ROMs come from Nintendo's first 3D console, released in 1996. It kept cartridges when rivals moved to CDs, added an analog stick and four controller ports, and sold about 33 million units.

Super Mario 64 and The Legend of Zelda: Ocarina of Time showed how 3D games could play, and many players still put them near the top of any list. GoldenEye 007, Mario Kart 64, Super Smash Bros., Banjo-Kazooie, Majora's Mask, Perfect Dark and Paper Mario made the console a favorite for local multiplayer.

N64 games on ROMSVAULT are No-Intro .z64 files, plus hacks and fan translations. Project64 and RetroArch run them on PC, and Mupen64Plus FZ is the usual choice on Android.

### Setup guide

Nintendo 64 games here are No-Intro cartridge dumps, usually in `.z64` format.

- **PC (Windows)**: Project64, simple64 or Rosalie’s Mupen GUI.
- **PC (macOS, Linux)**: RetroArch with the Mupen64Plus-Next core.
- **Android**: Mupen64Plus FZ, or RetroArch.

Open the `.z64` file; no BIOS is needed. The N64 controller has an analog stick and C buttons, so map the C buttons to the right stick of a modern gamepad. If a game shows graphics glitches, switch the video plugin, or use the ParaLLEl core in RetroArch for the most accurate picture.

---

## Wii U

`/roms/wii-u/` · 842 published roms

### Description

Wii U ROMs come from Nintendo's 2012 console, built around a GamePad with its own touch screen. It sold only about 13.5 million units, but it has a strong library, much of which later moved to the Switch.

Mario Kart 8, Super Mario 3D World, Splatoon, Super Smash Bros. for Wii U, Bayonetta 2, Xenoblade Chronicles X, Pikmin 3 and The Legend of Zelda: Breath of the Wild all appeared on the Wii U, and a few of them never left it.

Wii U games here are in WUX format. Cemu, one of the most mature console emulators, plays most of them on PC in full HD and beyond.

### Setup guide

Wii U games here are in **WUX**, a compressed form of the original WUD disc image.

- **PC (Windows, Linux, macOS)**: [Cemu](/emulators/wii-u/cemu/).

Cemu opens `.wux` files directly. Encrypted disc images need their disc key in Cemu’s `keys.txt`; without it the game will not load. To get an uncompressed image for a real Wii U, decompress the WUX with [WudCompress](https://gbatemp.net/threads/wii-u-image-wud-compression-tool.397901/); the result matches the Redump checksum.

---

## Sega Dreamcast

`/roms/dreamcast/` · 711 published roms

### Description

Sega Dreamcast ROMs cover the last home console Sega made. The Dreamcast launched in Japan in November 1998 and in North America on 9.9.99, and it was the first console with a built-in modem for online play. Sega stopped making it in 2001 after selling around 9 million units, but its library still holds up.

Games came on GD-ROM discs that held about 1 GB, and the VMU memory card had its own small screen. The standouts include Shenmue, Jet Set Radio, Soulcalibur, Crazy Taxi, Skies of Arcadia, Sonic Adventure and Phantasy Star Online, plus a long list of arcade ports from Sega and Capcom.

Dreamcast emulation is in good shape. Flycast and Redream run most of the library at full speed on a PC or a mid-range Android phone, and both can upscale the picture well beyond the original 480p.

### Setup guide

Dreamcast games here are disc images, many of them in **CHD**, a compressed format that keeps the full disc in a single file.

- **PC (Windows, macOS, Linux)**: Flycast, standalone or as a RetroArch core, or Redream.
- **Android**: Flycast or Redream from the Play Store.

Open the `.chd`, `.gdi` or `.cdi` file directly; there is nothing to extract. Both emulators start most games without a BIOS file, but Flycast is more compatible when you give it `dc_boot.bin` and `dc_flash.bin` in its data folder. Save files go to an emulated VMU, so you can keep progress across sessions.

---

## TeknoParrot

`/roms/teknoparrot/` · 399 published roms

### Description

TeknoParrot ROMs are arcade games, not console games. TeknoParrot is a Windows loader that runs titles from PC-based arcade boards such as Sega Lindbergh and RingEdge, Taito Type X and Namco System ES. These machines were basically PCs inside a cabinet, so instead of emulating the hardware, TeknoParrot patches the game to run on a normal Windows computer.

That covers a lot of the arcade scene from the mid-2000s onward: racing games like Initial D Arcade Stage and Mario Kart Arcade GP DX, light gun shooters like The House of the Dead 4, and fighting games from Arc System Works and SNK. Many of these never had a home release.

Each game has its own settings for controls, wheels and light guns, and some need a recent graphics card. Check the TeknoParrot compatibility list before you download a large game.

### Setup guide

TeknoParrot games are PC-based arcade dumps. Each one is a folder with the game’s own executable and data.

- **PC (Windows)**: TeknoParrot.

Extract the game, open TeknoParrotUI, choose Add Game and pick the matching profile, then point it to the game’s executable. Set your controls, wheel or light gun under Controller Setup before the first launch. Games do not work on every PC, so read the notes for your game on the [TeknoParrot compatibility list](https://teknoparrot.com/Compatibility); it also has fixes for common problems.

---

## Sega CD

`/roms/sega-cd/` · 230 published roms

### Description

Sega CD ROMs come from the CD add-on for the Sega Genesis, sold as the Mega-CD in Japan and Europe. It launched in Japan in 1991 and in North America in 1992, and sold a little over 2 million units. Plugged into the Genesis, it added a faster CPU, extra graphics features, CD audio and room for full-motion video.

The add-on is remembered for FMV games like Night Trap, but its best games are elsewhere. Sonic CD, Snatcher, Lunar: The Silver Star, Lunar: Eternal Blue, Popful Mail and Final Fight CD are all here, many with CD-quality soundtracks the cartridge versions never had.

All Sega CD games on ROMSVAULT are in CHD format. You can play them with Genesis Plus GX in RetroArch or with PicoDrive, as long as you add a Sega CD BIOS for your region.

### Setup guide

Sega CD games here are in **CHD** format, one compressed file per disc.

- **PC and Android**: Genesis Plus GX core in RetroArch, or PicoDrive.
- **Windows only**: Kega Fusion also runs Sega CD games.

Sega CD needs a BIOS, and it has to match the game region. For Genesis Plus GX, put `bios_CD_U.bin` (USA), `bios_CD_E.bin` (Europe) and `bios_CD_J.bin` (Japan) in the RetroArch `system` folder, then load the `.chd` file. If a game stops at a black screen, the BIOS for its region is usually missing.

---

## Nintendo Virtual Boy

`/roms/nintendo-virtual-boy/` · 5 published roms

### Description

Nintendo Virtual Boy ROMs come from one of Nintendo's strangest consoles. Released in 1995 in Japan and North America, the Virtual Boy sat on a stand and showed stereoscopic 3D in red and black through two small displays. It never reached Europe and was discontinued within a year, with only 22 games released.

The 3D effect still impresses, and a few games are worth playing: Virtual Boy Wario Land, Mario's Tennis, Red Alarm, Teleroboxer and Jack Bros. The catalog on ROMSVAULT is small and focuses on fan translations of games that only came out in Japan.

Mednafen and its RetroArch core Beetle VB play every Virtual Boy game. You can view them in red and black, or turn on anaglyph or side-by-side 3D if you have the glasses or a VR headset.

### Setup guide

Virtual Boy games are small `.vb` files and do not need a BIOS.

- **PC and Android**: Mednafen, or the Beetle VB core in RetroArch.
- **Nintendo 3DS**: the homebrew emulator Red Viper uses the 3DS screen for real 3D.

In Beetle VB, open Core Options and set the 3D mode. Anaglyph works with red and blue glasses, side-by-side works with a phone VR headset, and the default shows a single flat image. The original red palette is easy on the eyes in short sessions; switch to a grey or white palette if you play for longer.

---

## Nintendo Pokemon Mini

`/roms/nintendo-pokemon-mini/` · 5 published roms

### Description

Pokémon Mini ROMs come from the smallest cartridge console Nintendo ever made. The Pokémon Mini came out in 2001 and was smaller than a deck of cards, with a 96×64 monochrome screen, a rumble motor, an infrared port and a shock sensor that reacted when you tapped the device.

Only ten games were released, and several of them never left Japan. Pokémon Party Mini, Pokémon Pinball Mini, Pokémon Puzzle Collection and Pokémon Zany Cards were sold in the West, while Pokémon Race Mini, Togepi no Daibouken and Pichu Bros. Mini stayed in Japan. The games on ROMSVAULT are fan translations of those Japan-only releases.

The PokeMini emulator plays the whole library on PC and Android, and it simulates the rumble and shock sensor with on-screen effects.

### Setup guide

Pokémon Mini games are tiny `.min` files.

- **PC and Android**: PokeMini, standalone or as a RetroArch core.

Load the `.min` file and the game starts right away. A BIOS file (`bios.min`) is optional; PokeMini includes a free replacement, but the original BIOS gives the most accurate results. Some games ask you to shake or tap the device. In PokeMini this is mapped to a button, so check the controls menu if a game seems stuck on a prompt.

---

## Nintendo Switch

`/roms/switch/` · 0 published roms

### Description

Nintendo Switch ROMs belong to Nintendo's hybrid console, released in March 2017. The Switch works as a home console on the TV and as a handheld, with detachable Joy-Con controllers, and it has sold more than 140 million units, making it one of the best-selling consoles ever.

Its library mixes big Nintendo releases with a huge indie catalog. The Legend of Zelda: Breath of the Wild and Tears of the Kingdom, Mario Kart 8 Deluxe, Super Smash Bros. Ultimate, Animal Crossing: New Horizons and the Pokémon games sit next to hits like Hades and Stardew Valley.

Switch games can be played on PC through emulators. Yuzu and Ryujinx were the two main projects until both stopped development in 2024, and community forks continue their work. A fairly modern CPU and graphics card are needed for most 3D games.

### Setup guide

Switch games come as **NSP** or **XCI** files. Updates and DLC are separate NSP files.

- **PC (Windows, Linux, macOS)**: [Ryujinx](/emulators/switch/ryujinx/) and its forks, or forks of [Yuzu](/emulators/switch/yuzu/).
- **Android**: Yuzu-based forks for phones with a recent Snapdragon chip.

Before any game will start, the emulator needs `prod.keys` and the system firmware, both dumped from a Switch console. Add them through the emulator menu, then open the game file. To install an update or DLC, use the emulator option for updates or DLC and select the extra NSP; do not open it as a game.

---

## Unknown

`/roms/unknown/` · 0 published roms

### Description

_None._

### Setup guide

_None._
