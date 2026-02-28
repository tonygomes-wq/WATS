<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Dispare Mensagens em Massa no WhatsApp com Alta Entrega e ZERO Bloqueios">
    <title>WATS - Disparo em Massa WhatsApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/landing.css">
</head>
<body class="bg-gray-900 text-white">
    <div class="scroll-progress" id="scrollProgress"></div>
    
    <!-- Navigation -->
    <nav class="fixed w-full top-0 z-50 bg-gray-900/95 backdrop-blur-sm border-b border-gray-800">
        <div class="container mx-auto px-4 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-bolt text-green-500 text-2xl"></i>
                    <span class="text-2xl font-bold">WATS MACIP</span>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#beneficios" class="hover:text-green-500 transition">Benefícios</a>
                    <a href="#recursos" class="hover:text-green-500 transition">Recursos</a>
                    <a href="#planos" class="hover:text-green-500 transition">Planos</a>
                    <a href="#faq" class="hover:text-green-500 transition">FAQ</a>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="hidden md:block hover:text-green-500 transition">Entrar</a>
                    <a href="login.php" class="btn-primary px-6 py-2 rounded-full text-white font-semibold">Começar Grátis</a>
                </div>
            </div>
        </div>
    </nav>
    
    <?php include 'includes/landing/hero.php'; ?>
    <?php include 'includes/landing/benefits.php'; ?>
    <?php include 'includes/landing/how_it_works.php'; ?>
    <?php include 'includes/landing/resources.php'; ?>
    <?php include 'includes/landing/comparison.php'; ?>
    <?php include 'includes/landing/testimonials.php'; ?>
    <?php include 'includes/landing/pricing.php'; ?>
    <?php include 'includes/landing/faq.php'; ?>
    <?php include 'includes/landing/footer.php'; ?>
    
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="assets/js/landing.js"></script>
</body>
</html>
