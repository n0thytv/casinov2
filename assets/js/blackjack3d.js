/**
 * N0THY CASINO - Blackjack 3D Engine (High Quality)
 * Three.js-based 3D rendering for Blackjack game
 */

class Blackjack3D {
    constructor(containerId, gameState) {
        this.container = document.getElementById(containerId);
        this.gameState = gameState;
        this.scene = null;
        this.camera = null;
        this.renderer = null;
        this.cards = [];
        this.chips = [];
        this.table = null;
        this.dealerPosition = { x: 0, y: 0.05, z: -2.0 };
        this.playerPositions = [
            { x: -3.5, y: 0.05, z: 2.5 },
            { x: -1.8, y: 0.05, z: 3.2 },
            { x: 0, y: 0.05, z: 3.5 },
            { x: 1.8, y: 0.05, z: 3.2 },
            { x: 3.5, y: 0.05, z: 2.5 }
        ];

        this.init();
    }

    init() {
        // Scene setup
        this.scene = new THREE.Scene();
        this.scene.background = new THREE.Color(0x050505);
        this.scene.fog = new THREE.Fog(0x050505, 10, 30);

        // Camera setup (Adjusted for better framing)
        const aspect = this.container.clientWidth / this.container.clientHeight;
        this.camera = new THREE.PerspectiveCamera(40, aspect, 0.1, 50);
        this.camera.position.set(0, 8, 11); // Moved back and up
        this.camera.lookAt(0, 0, 0);

        // Renderer setup
        this.renderer = new THREE.WebGLRenderer({
            antialias: true,
            powerPreference: "high-performance"
        });
        this.renderer.setSize(this.container.clientWidth, this.container.clientHeight);
        this.renderer.shadowMap.enabled = true;
        this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        this.renderer.physicallyCorrectLights = true;
        this.renderer.outputEncoding = THREE.sRGBEncoding;
        this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
        this.renderer.toneMappingExposure = 1.0;
        this.container.appendChild(this.renderer.domElement);

        // Lighting
        this.setupLights();

        // Environment
        this.createEnvironment();

        // Table
        this.createTable();

        // Render game state
        this.renderGameState();

        // Animation loop
        this.animate();

        // Handle resize
        window.addEventListener('resize', () => this.onResize());
    }

    setupLights() {
        // Ambient light (warm)
        const ambient = new THREE.AmbientLight(0xffeeb1, 0.2); // Warm ambient
        this.scene.add(ambient);

        // Main Ceiling Light (Spotlight)
        const mainSpot = new THREE.SpotLight(0xffffff, 80);
        mainSpot.position.set(0, 8, 2);
        mainSpot.angle = Math.PI / 4;
        mainSpot.penumbra = 0.5;
        mainSpot.decay = 2;
        mainSpot.distance = 50;
        mainSpot.castShadow = true;
        mainSpot.shadow.mapSize.width = 4096; // Ultra High Res Shadows
        mainSpot.shadow.mapSize.height = 4096;
        mainSpot.shadow.bias = -0.00005;
        mainSpot.shadow.radius = 2; // Slightly sharper but clean
        this.scene.add(mainSpot);

        // Fill Light (Cooler)
        const fillLight = new THREE.PointLight(0xccddff, 15);
        fillLight.position.set(-5, 4, 5);
        this.scene.add(fillLight);

        // Rim Light (Warm/Gold)
        const rimLight = new THREE.PointLight(0xffaa00, 20);
        rimLight.position.set(5, 4, -5);
        this.scene.add(rimLight);
    }

    createEnvironment() {
        // Simple dark floor
        const floorGeo = new THREE.PlaneGeometry(50, 50);
        const floorMat = new THREE.MeshStandardMaterial({
            color: 0x050505,
            roughness: 0.8,
            metalness: 0.2
        });
        const floor = new THREE.Mesh(floorGeo, floorMat);
        floor.rotation.x = -Math.PI / 2;
        floor.position.y = -0.1;
        floor.receiveShadow = true;
        this.scene.add(floor);
    }

    createFeltTexture() {
        const canvas = document.createElement('canvas');
        canvas.width = 512;
        canvas.height = 512;
        const ctx = canvas.getContext('2d');

        // Base color
        ctx.fillStyle = '#1a5c1a'; // Dark green
        ctx.fillRect(0, 0, 512, 512);

        // Noise
        for (let i = 0; i < 50000; i++) {
            const x = Math.random() * 512;
            const y = Math.random() * 512;
            const opacity = Math.random() * 0.1;
            ctx.fillStyle = `rgba(255, 255, 255, ${opacity})`;
            ctx.fillRect(x, y, 1, 1);
        }

        // Darker vignette
        const grad = ctx.createRadialGradient(256, 256, 100, 256, 256, 300);
        grad.addColorStop(0, 'rgba(0,0,0,0)');
        grad.addColorStop(1, 'rgba(0,0,0,0.4)');
        ctx.fillStyle = grad;
        ctx.fillRect(0, 0, 512, 512);

        const tex = new THREE.CanvasTexture(canvas);
        tex.wrapS = THREE.RepeatWrapping;
        tex.wrapT = THREE.RepeatWrapping;
        tex.repeat.set(2, 2);
        return tex;
    }

    createTable() {
        const feltTexture = this.createFeltTexture();

        // 1. Base Cylinder (Wood structure underneath)
        const baseGeo = new THREE.CylinderGeometry(5.2, 5.2, 0.2, 64, 1, false, 0, Math.PI);
        const baseMat = new THREE.MeshStandardMaterial({ color: 0x221100, roughness: 0.6 });
        const base = new THREE.Mesh(baseGeo, baseMat);
        base.rotation.x = -Math.PI / 2; // Lay flat
        base.rotation.z = Math.PI; // Face +Z (Front) if geometry is 0..PI
        // Wait, if Geometry is 0..PI, it's +X..-X via +Y (in 2D). Rotated X-90 -> +X..-X via +Z.
        // So 0..PI is the FRONT semicircle.
        // But let's check Torus. Torus is full ring? 
        // TorusGeometry(radius, tube, radialSegments, tubularSegments, arc)
        // If arc=PI, it's semicircle.
        // Let's assume 0..PI is correct for front.

        // Actually, let's just use rotation.z = 0 first if it defaults to Front.
        // But earlier 'sideways' implies 0 was sideways.
        // 0 is +X..-X. That is SIDEWAYS (Left/Right).
        // Front/Back is +Z/-Z.
        // We want the curve to be along Z axis? No, we want curve to span from Left to Right, bulging forward (+Z).
        // So we want the arc to go from -X to +X via +Z.
        // Default 0..PI goes +X..-X via +Y (becomes +Z).
        // So that IS Front.
        // So rotation.z = 0 should be correct IF geometry is 0..PI.

        base.rotation.z = 0;
        base.position.y = -0.05;
        base.receiveShadow = true;
        this.scene.add(base);

        // 2. Felt Top (CircleGeometry for better texture mapping)
        const feltGeo = new THREE.CircleGeometry(5, 64, 0, Math.PI);
        const feltMat = new THREE.MeshStandardMaterial({
            map: feltTexture,
            roughness: 1.0,
            metalness: 0.0
        });
        this.table = new THREE.Mesh(feltGeo, feltMat);
        this.table.rotation.x = -Math.PI / 2;
        this.table.rotation.z = 0;
        this.table.position.y = 0.1;
        this.table.receiveShadow = true;
        this.scene.add(this.table);

        // Table edge (Dark Leather Armrest)
        const edgeGeometry = new THREE.TorusGeometry(5.2, 0.3, 16, 64, Math.PI);
        const edgeMaterial = new THREE.MeshStandardMaterial({
            color: 0x3d1a0f, // Dark leather
            roughness: 0.3,
            metalness: 0.1
        });
        const edge = new THREE.Mesh(edgeGeometry, edgeMaterial);
        edge.rotation.x = Math.PI / 2; // Torus is different?
        // Torus is XY plane ring.
        // rotation X 90 makes it XZ plane.
        // But need to match Cylinder.
        edge.rotation.x = -Math.PI / 2; // Consistent with cylinder
        edge.rotation.z = 0;
        edge.position.y = 0.1;
        this.scene.add(edge);

        // Accessories
        this.createAccessories();

        // Player Pos Markers
        this.playerPositions.forEach((pos) => {
            const marker = this.createAreaMarker(0xffffff, 0.05); // Tiny dot center
            marker.position.set(pos.x, 0.102, pos.z);
            marker.material.opacity = 0.1;
            this.scene.add(marker);
        });
    }

    createAccessories() {
        // Card Shoe (Right side)
        const shoeGeo = new THREE.BoxGeometry(1, 0.5, 2);
        const shoeMat = new THREE.MeshStandardMaterial({ color: 0x111111, roughness: 0.2 });
        const shoe = new THREE.Mesh(shoeGeo, shoeMat);
        shoe.position.set(-3.8, 0.35, -1);
        shoe.rotation.y = 0.3;
        shoe.castShadow = true;
        this.scene.add(shoe);

        // Face of shoe (where cards come out)
        const frontGeo = new THREE.BoxGeometry(1, 0.45, 0.1);
        const frontMat = new THREE.MeshStandardMaterial({ color: 0x333333 });
        const front = new THREE.Mesh(frontGeo, frontMat);
        front.position.set(0, 0, 1); // Local
        shoe.add(front);

        // Discard Tray (Left side)
        const trayGeo = new THREE.BoxGeometry(1, 0.2, 1.5);
        const trayMat = new THREE.MeshStandardMaterial({ color: 0x880000, transparent: true, opacity: 0.6, roughness: 0.1 });
        const tray = new THREE.Mesh(trayGeo, trayMat);
        tray.position.set(3.8, 0.2, -1);
        tray.rotation.y = -0.3;
        tray.castShadow = true;
        this.scene.add(tray);
    }

    createAreaMarker(color, size, text = null) {
        const geometry = new THREE.CircleGeometry(size, 64);
        const material = new THREE.MeshStandardMaterial({
            color: color,
            transparent: true,
            opacity: 0.15,
            side: THREE.BackSide // Visible from top
        });
        const marker = new THREE.Mesh(geometry, material);
        marker.rotation.x = -Math.PI / 2;
        return marker;
    }

    createCardTexture(suit, value, faceUp) {
        const canvas = document.createElement('canvas');
        canvas.width = 256;
        canvas.height = 356;
        const ctx = canvas.getContext('2d');

        // Rounded corners clip
        const r = 10;
        ctx.beginPath();
        ctx.moveTo(r, 0);
        ctx.lineTo(256 - r, 0);
        ctx.quadraticCurveTo(256, 0, 256, r);
        ctx.lineTo(256, 356 - r);
        ctx.quadraticCurveTo(256, 356, 256 - r, 356);
        ctx.lineTo(r, 356);
        ctx.quadraticCurveTo(0, 356, 0, 356 - r);
        ctx.lineTo(0, r);
        ctx.quadraticCurveTo(0, 0, r, 0);
        ctx.closePath();
        ctx.clip();

        if (faceUp && suit !== 'hidden') {
            // White Face
            ctx.fillStyle = '#f8f9fa';
            ctx.fillRect(0, 0, 256, 356);

            const isRed = (suit === 'hearts' || suit === 'diamonds');
            ctx.fillStyle = isRed ? '#d32f2f' : '#212529';
            const symbol = { 'hearts': '♥', 'diamonds': '♦', 'clubs': '♣', 'spades': '♠' }[suit] || '?';

            // Corner Value
            ctx.font = 'bold 36px "Segoe UI", Arial, sans-serif';
            ctx.fillText(value, 20, 50);
            ctx.font = '32px "Segoe UI", sans-serif';
            ctx.fillText(symbol, 20, 90);

            // Center
            ctx.font = '100px "Segoe UI", sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(symbol, 128, 200);

            // Corner Value (Rotated)
            ctx.save();
            ctx.translate(236, 306);
            ctx.rotate(Math.PI);
            ctx.font = 'bold 36px "Segoe UI", Arial, sans-serif';
            ctx.fillText(value, 0, 0);
            ctx.font = '32px "Segoe UI", sans-serif';
            ctx.fillText(symbol, 0, -40);
            ctx.restore();
        } else {
            // Card Back - Elegant Pattern
            ctx.fillStyle = '#2d1b4e'; // Dark Violet
            ctx.fillRect(0, 0, 256, 356);

            ctx.strokeStyle = '#ffd700'; // Gold
            ctx.lineWidth = 2;
            ctx.strokeRect(15, 15, 226, 326);

            // Cross hatch
            ctx.beginPath();
            ctx.lineWidth = 1;
            ctx.strokeStyle = 'rgba(255, 215, 0, 0.3)';
            for (let i = 0; i < 356; i += 20) {
                ctx.moveTo(0, i);
                ctx.lineTo(256, i + 20);

                ctx.moveTo(0, i + 20);
                ctx.lineTo(256, i);
            }
            ctx.stroke();

            // Logo center
            ctx.fillStyle = '#ffd700';
            ctx.font = 'bold 40px serif';
            ctx.textAlign = 'center';
            ctx.fillText('N0', 128, 170);
            ctx.fillText('THY', 128, 210);
        }

        const tex = new THREE.CanvasTexture(canvas);
        tex.anisotropy = this.renderer.capabilities.getMaxAnisotropy();
        return tex;
    }

    createCard(suit, value, faceUp = true) {
        const width = 0.7;
        const height = 1.0;
        const depth = 0.01;

        // Just a flat geometry for better performance/look with textures (Box is ok too but thin)
        const geometry = new THREE.BoxGeometry(width, depth, height);

        const frontTex = this.createCardTexture(suit, value, true);
        const backTex = this.createCardTexture(suit, value, false);
        const sideMat = new THREE.MeshStandardMaterial({ color: 0xffffff }); // Paper edges

        // Materials: Right, Left, Top(Face), Bottom(Back), Front, Back
        // But for BoxGeometry: 0(+x), 1(-x), 2(+y), 3(-y), 4(+z), 5(-z)
        // With depth being 'y' axis in previous logic, let's stick to Box(w, d, h) where y is thinner
        // So 2 is Top, 3 is Bottom

        const faceMat = new THREE.MeshStandardMaterial({ map: frontTex, roughness: 1.0, metalness: 0.0 });
        const backMat = new THREE.MeshStandardMaterial({ map: backTex, roughness: 1.0, metalness: 0.0 });

        // Note: UV mapping for Box might need adjustment, but CanvasTexture fits 0..1
        // Usually Box faces are distinct.
        // Let's rely on standard box mapping. Face is Up (+Y) in our logic if rotated X -90

        // Geometry constructed as Box(w, thickness, h).
        // Front face is +Z (index 4), Back is -Z (index 5)
        // Top is +Y (index 2), Bottom is -Y (index 3)
        // We lie cards flat on the table, so Y is Up in World.
        // But the Box is created oriented Up.
        // We said depth=0.01 is typically Z thickness in simpler engines, 
        // Here BoxGeometry(w, h, d) arguments are width, height, depth.
        // Let's allow BoxGeometry(0.7, 0.01, 1.0) -> flat on XZ plane if Y is thin logic... 
        // Wait, standard BoxGeometry is (width, height, depth).
        // If we want it flat on table: (width, thickness, height) -> (0.7, 0.01, 1.0)
        // Faces:
        // 0: +x (side)
        // 1: -x (side)
        // 2: +y (top/face)
        // 3: -y (bottom/back)
        // 4: +z (edge)
        // 5: -z (edge)

        const mats = [
            sideMat, sideMat,
            faceUp ? faceMat : backMat, // Top
            faceUp ? backMat : faceMat, // Bottom
            sideMat, sideMat
        ];

        const card = new THREE.Mesh(geometry, mats);
        card.castShadow = true;
        card.receiveShadow = true;
        card.userData = { suit, value, faceUp };

        // Initial orientation: Typically random slight jitter
        card.rotation.y = (Math.random() - 0.5) * 0.1;

        return card;
    }

    createChipStack(amount, position) {
        const stack = new THREE.Group();
        const chipHeight = 0.05;
        const radius = 0.22;

        const chipValues = [1000, 500, 100, 50, 25, 10];
        const colors = { 1000: '#ffd700', 500: '#8b00ff', 100: '#111111', 50: '#d32f2f', 25: '#2e7d32', 10: '#1565c0' };

        let remaining = amount;
        let currentY = 0;

        // Simple cylinder geometry shared
        const geo = new THREE.CylinderGeometry(radius, radius, chipHeight, 32);

        for (const val of chipValues) {
            while (remaining >= val) {
                const color = colors[val];
                // Striped texture for side
                const matBody = new THREE.MeshStandardMaterial({ color: color, roughness: 0.3, metalness: 0.1 });
                const matFace = new THREE.MeshStandardMaterial({
                    color: color,
                    roughness: 0.5,
                    map: this.createChipFaceTexture(val, color) // Top/Bottom
                });

                const chip = new THREE.Mesh(geo, [matBody, matFace, matFace]);
                chip.position.y = currentY + chipHeight / 2;
                chip.castShadow = true;
                chip.receiveShadow = true;

                // Add white stripes on side simulation (simple rings geometry or texture?)
                // Texture is better but let's keep it simple geometry for now or simple lines

                stack.add(chip);
                currentY += chipHeight;
                remaining -= val;
            }
        }

        stack.position.set(position.x, position.y, position.z);
        return stack;
    }

    createChipFaceTexture(value, colorStr) {
        const cvs = document.createElement('canvas');
        cvs.width = 128;
        cvs.height = 128;
        const ctx = cvs.getContext('2d');

        ctx.fillStyle = colorStr;
        ctx.fillRect(0, 0, 128, 128);

        // Dashed ring
        ctx.strokeStyle = 'white';
        ctx.lineWidth = 4;
        ctx.setLineDash([10, 10]);
        ctx.beginPath();
        ctx.arc(64, 64, 50, 0, Math.PI * 2);
        ctx.stroke();

        // Inner solid ring
        ctx.setLineDash([]);
        ctx.beginPath();
        ctx.arc(64, 64, 40, 0, Math.PI * 2);
        ctx.stroke();

        ctx.fillStyle = 'white';
        ctx.font = 'bold 30px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(value, 64, 64);

        const tex = new THREE.CanvasTexture(cvs);
        tex.anisotropy = 4;
        return tex;
    }

    animateDealCard(targetPosition, delay = 0) {
        return new Promise(resolve => {
            const card = this.createCard('hidden', '?', false);
            card.position.set(4, 2, -3); // Start from deck
            card.rotation.x = -Math.PI / 2;
            card.rotation.y = Math.PI;
            this.scene.add(card);
            this.cards.push(card);

            const startTime = Date.now() + delay;
            const duration = 600;
            const startPos = card.position.clone();

            const animate = () => {
                const elapsed = Date.now() - startTime;
                if (elapsed < 0) {
                    requestAnimationFrame(animate);
                    return;
                }
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3);
                card.position.lerpVectors(startPos, targetPosition, eased);
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    resolve(card);
                }
            };
            requestAnimationFrame(animate);
        });
    }

    animateWinner(playerIndex) {
        const pos = this.playerPositions[playerIndex] || this.playerPositions[0];
        for (let i = 0; i < 30; i++) {
            const geometry = new THREE.BoxGeometry(0.05, 0.05, 0.05);
            const material = new THREE.MeshBasicMaterial({ color: Math.random() > 0.5 ? 0xffd700 : 0x8b00ff });
            const particle = new THREE.Mesh(geometry, material);
            particle.position.set(pos.x + (Math.random() - 0.5) * 1.5, 0.5, pos.z + (Math.random() - 0.5) * 1.5);
            this.scene.add(particle);

            const startY = particle.position.y;
            const startTime = Date.now();
            const duration = 2000;
            const speedY = 2 + Math.random();

            const animateParticle = () => {
                const elapsed = Date.now() - startTime;
                const progress = elapsed / duration;
                if (progress >= 1) {
                    this.scene.remove(particle);
                    return;
                }
                particle.position.y = startY + (speedY * progress) - (4 * progress * progress);
                particle.rotation.x += 0.1;
                particle.rotation.z += 0.1;
                particle.material.opacity = 1 - progress;
                requestAnimationFrame(animateParticle);
            };
            setTimeout(() => requestAnimationFrame(animateParticle), i * 50);
        }
    }

    updateGameState(newState) {
        this.gameState = newState;
        this.renderGameState();
    }

    renderGameState() {
        // Clear
        this.cards.forEach(c => this.scene.remove(c));
        this.chips.forEach(c => this.scene.remove(c));
        this.cards = [];
        this.chips = [];

        if (!this.gameState) return;

        // Dealer
        (this.gameState.dealer_cards_visible || []).forEach((c, i) => {
            const card = this.createCard(c.suit, c.value, c.suit !== 'hidden');
            // Slight fan layout or straight
            card.position.set(
                this.dealerPosition.x + (i * 0.75) - 0.5,
                this.dealerPosition.y,
                this.dealerPosition.z
            );
            this.scene.add(card);
            this.cards.push(card);
        });

        // Players
        (this.gameState.players || []).forEach((p, idx) => {
            const pos = this.playerPositions[p.seat_number - 1] || this.playerPositions[idx];

            // Chips
            if (p.bet_amount > 0) {
                const stack = this.createChipStack(p.bet_amount, { x: pos.x, y: 0.1, z: pos.z - 0.8 });
                this.scene.add(stack);
                this.chips.push(stack);
            }

            // Cards
            (p.cards || []).forEach((c, cIdx) => {
                const card = this.createCard(c.suit, c.value, true);
                card.position.set(
                    pos.x + (cIdx * 0.4) - 0.2,
                    pos.y + (cIdx * 0.01), // Slight stack up to avoid z-fight
                    pos.z
                );
                this.scene.add(card);
                this.cards.push(card);
            });

            // Split cards handler could go here
        });
    }

    animate() {
        requestAnimationFrame(() => this.animate());
        this.renderer.render(this.scene, this.camera);
    }

    onResize() {
        if (!this.container || !this.camera || !this.renderer) return;
        const width = this.container.clientWidth;
        const height = this.container.clientHeight;
        this.camera.aspect = width / height;
        this.camera.updateProjectionMatrix();
        this.renderer.setSize(width, height);
    }
}

window.Blackjack3D = Blackjack3D;
