<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Selector de Horarios</title>
  <script src="https://cdn.tailwindcss.com "></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center px-4">

  <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl w-full">

    <!-- Tarjeta 2x2 -->
    <a href="index2.php" class="group block bg-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden transform hover:-translate-y-1 border-l-4 border-blue-500">
      <div class="p-8 text-center">
        <h2 class="text-2xl font-bold text-gray-800 group-hover:text-blue-600 transition">Horario 2x2</h2>
        <p class="mt-2 text-gray-600">Turnos rotativos de 2 días por empleado.</p>
        <div class="mt-4 inline-flex items-center text-blue-500 font-medium">
          Ver más
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1 group-hover:translate-x-1 transition-transform" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
          </svg>
        </div>
      </div>
    </a>

    <!-- Tarjeta 4x4 -->
    <a href="index4.php" class="group block bg-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden transform hover:-translate-y-1 border-l-4 border-purple-500">
      <div class="p-8 text-center">
        <h2 class="text-2xl font-bold text-gray-800 group-hover:text-purple-600 transition">Horario 4x4</h2>
        <p class="mt-2 text-gray-600">Turnos rotativos de 4 días por empleado.</p>
        <div class="mt-4 inline-flex items-center text-purple-500 font-medium">
          Ver más
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 ml-1 group-hover:translate-x-1 transition-transform" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
          </svg>
        </div>
      </div>
    </a>

  </div>

</body>
</html>