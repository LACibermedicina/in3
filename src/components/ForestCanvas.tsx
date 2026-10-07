'use client';

/* ============================================================================
   Floresta isometrica low-poly do IN3.
   Semeadura voxel amplificada -> cada projeto gera uma arvore-bonsai UNICA
   (silhueta, cor, casa na arvore, andaimes), ilhas flutuantes, cachoeiras e nuvens.
   ========================================================================== */

import { useMemo, useRef, useState } from 'react';
import { Canvas, useFrame, type ThreeEvent } from '@react-three/fiber';
import { OrbitControls, Html, PerspectiveCamera } from '@react-three/drei';
import * as THREE from 'three';

export type ProjectLite = {
  id: string; slug: string; name: string; status: 'initial' | 'building' | 'done';
  progress: number; roi: number; heat: 'warm' | 'cool'; tokensRaised: number;
  seed: number; x: number; z: number; released: boolean;
};

/* --------- gerador pseudoaleatorio deterministico (a "semente" do voxel) --- */
function rng(seed: number) {
  let s = seed >>> 0;
  return () => {
    s = (s * 1664525 + 1013904223) >>> 0;
    return s / 4294967296;
  };
}

const PALETTES = {
  warm: ['#FF8A5B', '#FFB347', '#E85D75', '#FF6F61', '#F4A259'],
  cool: ['#4EA8DE', '#56C596', '#7FB2F0', '#3E8E7E', '#8E7CC3']
};

const LIGHT_COUNTS = [6, 3];
const P = { size: 21, grid: 21 };

export default function ForestCanvas({
  projects, onSelect
}: { projects: ProjectLite[]; onSelect: (p: ProjectLite) => void }) {
  return (
    <Canvas dpr={[1, 1.75]} shadows gl={{ antialias: true }}>
      <PerspectiveCamera makeDefault position={[16, 15, 16]} fov={32} />
      <OrbitControls
        enablePan
        minPolarAngle={0.55}
        maxPolarAngle={1.15}
        minDistance={12}
        maxDistance={42}
        target={[0, 0, 0]}
      />
      <color attach="background" args={['#0d1430']} />
      <fog attach="fog" args={['#0d1430', 30, 68]} />

      <hemisphereLight args={['#cfe6ff', '#3a2f1f', 0.75]} />
      <directionalLight
        position={[12, 18, 8]}
        intensity={2.1}
        color="#ffd9a8"
        castShadow
        shadow-mapSize={[2048, 2048]}
      />
      <directionalLight position={[-10, 6, -12]} intensity={0.55} color="#7fb2f0" />

      <CloudBank />
      <FloatingIslands />

      {projects.map((p) => (
        <ProjectTree key={p.id} p={p} onSelect={onSelect} />
      ))}

      <Avatars count={3} />
    </Canvas>
  );
}

/* ------------------------------- nuvens ---------------------------------- */
function CloudBank() {
  const group = useRef<THREE.Group>(null);
  const clouds = useMemo(
    () =>
      Array.from({ length: 12 }, (_, i) => {
        const r = rng(700 + i * 131);
        return {
          pos: [(r() - 0.5) * 46, 10 + r() * 8, (r() - 0.5) * 46] as [number, number, number],
          scale: 1.6 + r() * 2.2,
          speed: 0.12 + r() * 0.25
        };
      }),
    []
  );
  useFrame((_, dt) => {
    if (!group.current) return;
    group.current.children.forEach((c, i) => {
      c.position.x += clouds[i].speed * dt;
      if (c.position.x > 26) c.position.x = -26;
    });
  });
  return (
    <group ref={group}>
      {clouds.map((c, i) => (
        <group key={i} position={c.pos} scale={c.scale}>
          {[[0, 0, 0, 1], [1.1, 0.15, 0.2, 0.72], [-1.05, -0.1, -0.15, 0.66], [0.2, 0.5, 0.6, 0.5]].map(
            (s, j) => (
              <mesh key={j} position={[s[0], s[1], s[2]]} castShadow={false}>
                <icosahedronGeometry args={[s[3], 1]} />
                <meshStandardMaterial color="#ffffff" roughness={1} flatShading transparent opacity={0.85} />
              </mesh>
            )
          )}
        </group>
      ))}
    </group>
  );
}

/* --------------------------- ilhas flutuantes ---------------------------- */
function FloatingIslands() {
  const list = useMemo(
    () =>
      Array.from({ length: 5 }, (_, i) => {
        const r = rng(3100 + i * 77);
        return {
          pos: [(r() - 0.5) * 40, -3.2 - r() * 2.4, (r() - 0.5) * 40] as [number, number, number],
          s: 3 + r() * 2.6,
          water: i % 2 === 0
        };
      }),
    []
  );
  return (
    <group>
      {/* ilha central: base do mapa */}
      <BaseIsland size={26} y={-1.1} water />
      {list.map((l, i) => (
        <BaseIsland key={i} size={l.s} y={l.pos[1]} offset={[l.pos[0], l.pos[2]]} water={l.water} />
      ))}
    </group>
  );
}

function BaseIsland({
  size, y, offset = [0, 0], water = false
}: { size: number; y: number; offset?: [number, number]; water?: boolean }) {
  return (
    <group position={[offset[0], y, offset[1]]}>
      {/* grama */}
      <mesh receiveShadow position={[0, 0, 0]}>
        <cylinderGeometry args={[size / 2, size / 2 - 0.25, 0.9, 9]} />
        <meshStandardMaterial color="#6FCF97" flatShading roughness={0.95} />
      </mesh>
      {/* terra abaixo */}
      <mesh position={[0, -1.5, 0]} receiveShadow>
        <coneGeometry args={[size / 2 - 0.2, 3.4, 9]} />
        <meshStandardMaterial color="#6b4a2f" flatShading roughness={1} />
      </mesh>
      {water && (
        <mesh position={[0, 0.46, 0]} rotation={[-Math.PI / 2, 0, 0]}>
          <circleGeometry args={[size / 2 - 1.1, 9]} />
          <meshStandardMaterial color="#59b7e8" transparent opacity={0.5} roughness={0.15} metalness={0.4} />
        </mesh>
      )}
    </group>
  );
}

/* ------------------------- uma arvore por projeto ------------------------ */
function ProjectTree({ p, onSelect }: { p: ProjectLite; onSelect: (p: ProjectLite) => void }) {
  const [hover, setHover] = useState(false);
  const group = useRef<THREE.Group>(null);

  const spec = useMemo(() => {
    const r = rng(p.seed);
    const pal = PALETTES[p.heat];
    const layers = 3 + Math.floor(r() * 3);            // 3..5 camadas -> silhueta unica
    const height = 2.4 + r() * 2.6;
    const twist = (r() - 0.5) * 0.5;
    const leaf = pal[Math.floor(r() * pal.length)];
    const leaf2 = pal[Math.floor(r() * pal.length)];
    const house = { scale: 0.62 + r() * 0.35, roof: pal[Math.floor(r() * pal.length)] };
    return { layers, height, twist, leaf, leaf2, house, pal };
  }, [p.seed, p.heat]);

  useFrame((state) => {
    if (!group.current) return;
    const t = state.clock.elapsedTime;
    group.current.rotation.z = Math.sin(t * 0.7 + p.seed) * 0.012;
    const s = hover ? 1.06 : 1;
    group.current.scale.setScalar(THREE.MathUtils.lerp(group.current.scale.x, s, 0.12));
  });

  const done = p.status === 'done';
  const building = p.status === 'building';

  return (
    <group
      ref={group}
      position={[p.x, 0, p.z]}
      onPointerOver={(e: ThreeEvent<PointerEvent>) => { e.stopPropagation(); setHover(true); document.body.style.cursor = 'pointer'; }}
      onPointerOut={() => { setHover(false); document.body.style.cursor = 'auto'; }}
      onClick={(e: ThreeEvent<MouseEvent>) => { e.stopPropagation(); onSelect(p); }}
    >
      {/* tronco (bonsai: levemente torto) */}
      <mesh castShadow receiveShadow position={[0, spec.height / 2, 0]} rotation={[0, 0, spec.twist * 0.25]}>
        <cylinderGeometry args={[0.16, 0.3, spec.height, 6]} />
        <meshStandardMaterial color="#8a5a3b" flatShading roughness={1} />
      </mesh>

      {/* folhagem em camadas -> nunca se repete */}
      {Array.from({ length: spec.layers }, (_, i) => {
        const t = i / Math.max(1, spec.layers - 1);
        const rad = (1.5 - t * 0.85) * spec.house.scale + 0.35;
        return (
          <mesh
            key={i}
            castShadow
            position={[(i % 2 ? 1 : -1) * 0.24 * (1 - t), spec.height + 0.25 + i * 0.55, Math.sin(i + p.seed) * 0.2]}
          >
            <icosahedronGeometry args={[rad, 0]} />
            <meshStandardMaterial
              color={i % 2 ? spec.leaf : spec.leaf2}
              flatShading
              roughness={0.8}
            />
          </mesh>
        );
      })}

      {/* casa na arvore: concluida ou em construcao */}
      {done ? (
        <Treehouse scale={spec.house.scale} roof={spec.house.roof} />
      ) : (
        <Scaffold scale={spec.house.scale} progress={p.progress} />
      )}

      {/* barra de progresso holografica na base */}
      <ProgressHolo progress={p.progress} />

      {/* faisca dourada: terreno disponivel para aporte */}
      {!done && <GoldToken y={spec.height + 2.6} />}

      {/* animais quando o projeto esta em uso */}
      {done && <Critters />}

      {hover && (
        <Html position={[0, spec.height + 3.4, 0]} center distanceFactor={16}>
          <div className="pointer-events-none w-56 rounded-xl border border-amber-300/40 bg-slate-950/90 p-3 text-[11px] text-slate-100 shadow-xl">
            <div className="font-bold text-amber-200">{p.name}</div>
            <div className="mt-1 flex justify-between"><span className="text-slate-400">Conclusao</span><span>{p.progress}%</span></div>
            <div className="flex justify-between"><span className="text-slate-400">ROI</span><span className={p.heat === 'warm' ? 'text-orange-300' : 'text-sky-300'}>{p.roi}%</span></div>
            <div className="flex justify-between"><span className="text-slate-400">Tokens</span><span>{p.tokensRaised.toLocaleString('pt-BR')}</span></div>
            <div className="mt-1 text-[10px] text-slate-500">clique para abrir o painel</div>
          </div>
        </Html>
      )}
    </group>
  );
}

function Treehouse({ scale, roof }: { scale: number; roof: string }) {
  return (
    <group position={[0, 1.55, 0]} scale={scale}>
      <mesh castShadow receiveShadow>
        <boxGeometry args={[1.5, 1.05, 1.5]} />
        <meshStandardMaterial color="#c8a06a" flatShading roughness={0.9} />
      </mesh>
      <mesh castShadow position={[0, 0.95, 0]} rotation={[0, Math.PI / 4, 0]}>
        <coneGeometry args={[1.35, 0.95, 4]} />
        <meshStandardMaterial color={roof} flatShading roughness={0.75} />
      </mesh>
      <mesh position={[0, 0.12, 0.78]}>
        <boxGeometry args={[0.3, 0.55, 0.06]} />
        <meshStandardMaterial color="#5a3a22" flatShading />
      </mesh>
      {/* escada de corda */}
      <mesh position={[0.86, -0.7, 0.55]} rotation={[0.18, 0, 0.12]}>
        <boxGeometry args={[0.1, 1.7, 0.34]} />
        <meshStandardMaterial color="#a8793f" flatShading />
      </mesh>
    </group>
  );
}

function Scaffold({ scale, progress }: { scale: number; progress: number }) {
  const poles = [[-0.8, -0.8], [0.8, -0.8], [-0.8, 0.8], [0.8, 0.8]];
  return (
    <group position={[0, 1.35, 0]} scale={scale}>
      {poles.map((pos, i) => (
        <mesh key={i} castShadow position={[pos[0], 0, pos[1]]}>
          <boxGeometry args={[0.11, 2.1, 0.11]} />
          <meshStandardMaterial color="#9a6b3d" flatShading />
        </mesh>
      ))}
      {[0.3, 1.0].map((y, i) => (
        <group key={i}>
          <mesh position={[0, y, -0.8]}><boxGeometry args={[1.7, 0.09, 0.09]} /><meshStandardMaterial color="#b98a52" flatShading /></mesh>
          <mesh position={[0, y, 0.8]}><boxGeometry args={[1.7, 0.09, 0.09]} /><meshStandardMaterial color="#b98a52" flatShading /></mesh>
          <mesh position={[-0.8, y, 0]}><boxGeometry args={[0.09, 0.09, 1.7]} /><meshStandardMaterial color="#b98a52" flatShading /></mesh>
          <mesh position={[0.8, y, 0]}><boxGeometry args={[0.09, 0.09, 1.7]} /><meshStandardMaterial color="#b98a52" flatShading /></mesh>
        </group>
      ))}
      {/* volume parcial da casa conforme o progresso */}
      <mesh position={[0, 1.15, 0]} scale={[1, Math.max(0.12, progress / 200), 1]}>
        <boxGeometry args={[1.3, 0.75, 1.3]} />
        <meshStandardMaterial color="#d8b183" flatShading />
      </mesh>
      <mesh position={[0.7, -1.0, 0.7]} rotation={[0.22, 0, 0.16]}>
        <boxGeometry args={[0.11, 2.0, 0.4]} />
        <meshStandardMaterial color="#a8793f" flatShading />
      </mesh>
    </group>
  );
}

function ProgressHolo({ progress }: { progress: number }) {
  const w = 1.9;
  return (
    <group position={[0, 0.55, 0]}>
      <mesh>
        <boxGeometry args={[w, 0.07, 0.07]} />
        <meshStandardMaterial color="#123" transparent opacity={0.55} />
      </mesh>
      <mesh position={[-w / 2 + (w * progress) / 200, 0.02, 0]}>
        <boxGeometry args={[(w * progress) / 100, 0.09, 0.09]} />
        <meshStandardMaterial color={progress > 70 ? '#7CFFC4' : '#FFC53D'} emissive={progress > 70 ? '#7CFFC4' : '#FFC53D'} emissiveIntensity={1.4} toneMapped={false} />
      </mesh>
    </group>
  );
}

function GoldToken({ y }: { y: number }) {
  const ref = useRef<THREE.Mesh>(null);
  useFrame((s) => {
    if (!ref.current) return;
    ref.current.rotation.y = s.clock.elapsedTime * 1.1;
    ref.current.position.y = y + Math.sin(s.clock.elapsedTime * 1.6) * 0.18;
  });
  return (
    <mesh ref={ref} position={[0, y, 0]}>
      <octahedronGeometry args={[0.3, 0]} />
      <meshStandardMaterial color="#FFC53D" emissive="#FFC53D" emissiveIntensity={0.9} metalness={0.7} roughness={0.25} toneMapped={false} />
    </mesh>
  );
}

function Critters() {
  const g = useRef<THREE.Group>(null);
  useFrame((s) => {
    if (g.current) g.current.rotation.y = s.clock.elapsedTime * 0.9;
  });
  return (
    <group ref={g} position={[0, 0.55, 0]}>
      <mesh position={[1.5, 0, 0]} castShadow>
        <icosahedronGeometry args={[0.2, 0]} />
        <meshStandardMaterial color="#f6f2ea" flatShading />
      </mesh>
      <mesh position={[1.5, 0.26, 0]} rotation={[0, 0, 0.3]}>
        <coneGeometry args={[0.07, 0.3, 4]} />
        <meshStandardMaterial color="#f6f2ea" flatShading />
      </mesh>
      <mesh position={[-1.5, 0.35, 0.4]} rotation={[0, 0, 0]}>
        <coneGeometry args={[0.12, 0.44, 3]} />
        <meshStandardMaterial color="#8fd2ff" flatShading />
      </mesh>
    </group>
  );
}

/* ------------------------ avatares low-poly andando ---------------------- */
function Avatars({ count }: { count: number }) {
  const refs = useRef<(THREE.Group | null)[]>([]);
  const list = useMemo(
    () => Array.from({ length: count }, (_, i) => ({ r: 9 + i * 2.6, speed: 0.16 - i * 0.035, hat: i % 2 === 0, pack: i % 2 !== 0 })),
    [count]
  );
  useFrame((s) => {
    const t = s.clock.elapsedTime;
    refs.current.forEach((g, i) => {
      if (!g) return;
      const a = t * list[i].speed + i * 2.1;
      g.position.set(Math.cos(a) * list[i].r, 0.5, Math.sin(a) * list[i].r);
      g.rotation.y = -a + Math.PI / 2;
      g.position.y = 0.5 + Math.abs(Math.sin(t * 4 + i)) * 0.07;
    });
  });
  return (
    <group>
      {list.map((v, i) => (
        <group key={i} ref={(el) => { refs.current[i] = el; }}>
          <mesh castShadow position={[0, 0.3, 0]}>
            <capsuleGeometry args={[0.16, 0.42, 3, 6]} />
            <meshStandardMaterial color={['#ffd166', '#8ecae6', '#c7f9cc'][i % 3]} flatShading />
          </mesh>
          <mesh castShadow position={[0, 0.75, 0]}>
            <boxGeometry args={[0.28, 0.28, 0.28]} />
            <meshStandardMaterial color="#f0c9a0" flatShading />
          </mesh>
          {v.hat && (
            <mesh position={[0, 0.92, 0]}>
              <cylinderGeometry args={[0.19, 0.19, 0.1, 8]} />
              <meshStandardMaterial color="#FFC53D" flatShading />
            </mesh>
          )}
          {v.pack && (
            <mesh position={[0, 0.4, -0.2]}>
              <boxGeometry args={[0.24, 0.3, 0.14]} />
              <meshStandardMaterial color="#6b4a2f" flatShading />
            </mesh>
          )}
        </group>
      ))}
    </group>
  );
}

export const FOREST_LABELS = { gridLabel: `${P.grid}x${P.grid}`, size: P.size, lightCounts: LIGHT_COUNTS };
