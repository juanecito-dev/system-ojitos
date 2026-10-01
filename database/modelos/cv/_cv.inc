<?php

/*
 * Campos del currículum (CV_CAMPOS del sistema anterior), iguales en los 4 diseños.
 * En lugar de «Redactar con IA», el perfil y las funciones traen frases de ejemplo según el tipo de trabajo.
 */

$rubros = ['' => '(elige para ver frases de ejemplo)', 'ventas' => 'Ventas', 'atencion' => 'Atención al cliente', 'almacen' => 'Almacén y logística',
    'docencia' => 'Docencia y educación', 'construccion' => 'Construcción y oficios', 'administracion' => 'Administración y oficina', 'estudiante' => 'Estudiante o primer empleo'];

$perfil = [
    'ventas' => ['Profesional con experiencia en ventas y atención al público, orientado al logro de metas comerciales y a la fidelización de clientes.',
        'Destaco por mi facilidad de comunicación, capacidad de negociación y manejo de caja.'],
    'atencion' => ['Persona proactiva con experiencia en atención al cliente, capaz de resolver consultas y reclamos con amabilidad y rapidez.',
        'Me caracterizo por la paciencia, la buena comunicación y el trabajo en equipo.'],
    'almacen' => ['Trabajador responsable con experiencia en almacén, recepción y despacho de mercadería y control de inventarios.',
        'Ordenado, puntual y comprometido con el cuidado de los productos y la seguridad en el trabajo.'],
    'docencia' => ['Docente comprometido con el aprendizaje de los estudiantes, con experiencia en la planificación de sesiones y el uso de estrategias didácticas activas.',
        'Destaco por la vocación de servicio, la paciencia y la buena relación con padres de familia.'],
    'construccion' => ['Trabajador con experiencia en obras de construcción civil, en tareas de albañilería, acabados y apoyo en obra.',
        'Responsable, puntual y respetuoso de las normas de seguridad.'],
    'administracion' => ['Profesional con experiencia en labores administrativas, manejo de documentos, archivo y atención de usuarios.',
        'Manejo de herramientas de ofimática y organización de la información con orden y responsabilidad.'],
    'estudiante' => ['Estudiante responsable y con muchas ganas de aprender, en busca de mi primera oportunidad laboral para aportar con dedicación y puntualidad.',
        'Me adapto con facilidad a nuevos entornos y trabajo bien en equipo.'],
];

$funciones = [
    'ventas' => ['Atención al cliente: asesoría en la elección de productos según sus necesidades.', 'Ventas: cumplimiento de metas mensuales de venta.',
        'Caja: cobro, cuadre de caja diario y emisión de comprobantes.', 'Exhibición: orden y reposición de productos en tienda.'],
    'atencion' => ['Atención al público: orientación y respuesta a consultas de forma presencial y telefónica.', 'Reclamos: registro y seguimiento hasta su solución.',
        'Registro: actualización de datos de clientes en el sistema.', 'Trabajo en equipo: coordinación con las demás áreas para brindar un buen servicio.'],
    'almacen' => ['Recepción: control de la mercadería recibida contra guías y facturas.', 'Despacho: preparación y entrega de pedidos a tiempo.',
        'Inventarios: conteos periódicos y registro de entradas y salidas.', 'Orden: clasificación y almacenamiento seguro de los productos.'],
    'docencia' => ['Planificación: elaboración de unidades y sesiones de aprendizaje.', 'Enseñanza: dictado de clases con estrategias participativas.',
        'Evaluación: seguimiento del avance de los estudiantes y registro de notas.', 'Comunidad: comunicación constante con padres de familia.'],
    'construccion' => ['Albañilería: levantamiento de muros, tarrajeo y acabados.', 'Encofrado: armado de encofrados y vaciado de concreto.',
        'Apoyo en obra: traslado de materiales y limpieza del área de trabajo.', 'Seguridad: uso correcto de los equipos de protección personal.'],
    'administracion' => ['Documentación: redacción, registro y archivo de documentos.', 'Atención: orientación a usuarios y proveedores.',
        'Ofimática: elaboración de reportes en Word y Excel.', 'Apoyo administrativo: control de caja chica y compras menores.'],
    'estudiante' => ['Prácticas: apoyo en las tareas del área asignada.', 'Organización: cumplimiento de las tareas en los plazos indicados.',
        'Trabajo en equipo: colaboración en actividades y proyectos grupales.'],
];

return function (bool $conFoto) use ($rubros, $perfil, $funciones) {
    return [
        ['t' => 'h', 'label' => 'Datos principales'],
        ['id' => 'nombre', 'label' => 'Nombres y apellidos', 'req' => true, 'full' => true],
        ['id' => 'titulo', 'label' => 'Profesión o título (opcional)', 'full' => true, 'ph' => 'Ej.: Técnico en Computación e Informática'],
        ['id' => 'cel', 'label' => 'Celular', 'num' => true, 'ph' => '9xx xxx xxx'],
        ['id' => 'correo', 'label' => 'Correo electrónico'],
        ['id' => 'ciudadCv', 'label' => 'Ciudad', 'def' => '@ciudadPeru'],
        ['id' => 'linkedin', 'label' => 'LinkedIn o web (opcional)'],
        ...($conFoto ? [['id' => 'foto', 'label' => 'Foto tamaño carnet (opcional)', 'type' => 'photo', 'full' => true]] : []),
        ['t' => 'h', 'label' => 'Datos personales (no se usan en el formato Harvard)'],
        ['id' => 'dni', 'label' => 'DNI', 'num' => true],
        ['id' => 'nac', 'label' => 'Fecha de nacimiento', 'type' => 'date'],
        ['id' => 'ecCv', 'label' => 'Estado civil', 'type' => 'select', 'opts' => ['' => '(no indicar)', 'Soltero(a)' => 'Soltero(a)', 'Casado(a)' => 'Casado(a)',
            'Conviviente' => 'Conviviente', 'Viudo(a)' => 'Viudo(a)', 'Divorciado(a)' => 'Divorciado(a)']],
        ['id' => 'dirCv', 'label' => 'Dirección', 'full' => true],
        ['t' => 'h', 'label' => 'Perfil profesional'],
        ['id' => 'rubro', 'label' => 'Tipo de trabajo (solo para las frases de ejemplo, no sale en el CV)', 'type' => 'select', 'full' => true, 'opts' => $rubros],
        ['id' => 'perfil', 'label' => 'Resumen (3 o 4 líneas)', 'type' => 'area', 'full' => true, 'frasesPor' => 'rubro', 'frases' => $perfil,
            'ph' => 'Qué estudia o en qué trabaja, qué sabe hacer y qué busca. Toca una frase de ejemplo y ajústala.'],
        ['t' => 'lista', 'id' => 'edu', 'label' => 'Formación académica', 'item' => 'Estudio', 'add' => 'Agregar estudio', 'campos' => [
            ['id' => 'inst', 'label' => 'Institución', 'full' => true, 'ph' => 'Ej.: Universidad Nacional Agraria de la Selva'],
            ['id' => 'grado', 'label' => 'Carrera o grado', 'full' => true, 'ph' => 'Ej.: Ingeniería en Informática y Sistemas'],
            ['id' => 'lugar', 'label' => 'Lugar', 'ph' => 'Tingo María, Perú'],
            ['id' => 'desde', 'label' => 'Desde', 'ph' => 'Mar 2020'],
            ['id' => 'hasta', 'label' => 'Hasta', 'ph' => 'Dic 2024 / Presente'],
            ['id' => 'det', 'label' => 'Detalle (opcional, uno por línea)', 'type' => 'area', 'full' => true, 'ph' => "Ej.: Tercio superior\nCiclo X en curso"],
        ]],
        ['t' => 'lista', 'id' => 'exp', 'label' => 'Experiencia laboral', 'item' => 'Experiencia', 'add' => 'Agregar experiencia', 'campos' => [
            ['id' => 'cargo', 'label' => 'Cargo', 'full' => true],
            ['id' => 'emp', 'label' => 'Empresa o institución', 'full' => true],
            ['id' => 'lugar', 'label' => 'Lugar', 'ph' => 'Tingo María, Perú'],
            ['id' => 'desde', 'label' => 'Desde', 'ph' => 'Jul 2019'],
            ['id' => 'hasta', 'label' => 'Hasta', 'ph' => 'Presente'],
            ['id' => 'fun', 'label' => 'Funciones y logros (uno por línea; «Tema: detalle» sale con el tema en negrita)', 'type' => 'area', 'full' => true, 'modo' => 'lista',
                'frasesPor' => 'rubro', 'frases' => $funciones],
        ]],
        ['t' => 'lista', 'id' => 'cert', 'label' => 'Cursos y certificaciones', 'item' => 'Curso', 'add' => 'Agregar curso', 'campos' => [
            ['id' => 'nom', 'label' => 'Nombre del curso o certificado', 'full' => true],
            ['id' => 'inst', 'label' => 'Institución'],
            ['id' => 'fecha', 'label' => 'Fecha', 'ph' => 'May 2023'],
        ]],
        ['t' => 'h', 'label' => 'Habilidades, idiomas y referencias'],
        ['id' => 'hab', 'label' => 'Habilidades (una por línea; «Tema: detalle» sale con el tema en negrita)', 'type' => 'area', 'full' => true, 'modo' => 'lista',
            'ph' => "Ofimática: Word, Excel y PowerPoint (intermedio)\nAtención al cliente\nTrabajo en equipo",
            'frases' => ['Ofimática: Word, Excel y PowerPoint (intermedio)', 'Atención al cliente', 'Trabajo en equipo', 'Comunicación efectiva',
                'Puntualidad y responsabilidad', 'Manejo de caja y cuadre', 'Redes sociales y herramientas digitales', 'Licencia de conducir A-I']],
        ['id' => 'idiomas', 'label' => 'Idiomas', 'full' => true, 'ph' => 'Español (nativo), Inglés (básico)', 'frases' => ['Español (nativo)', 'Español (nativo), Inglés (básico)', 'Español (nativo), Quechua (intermedio)']],
        ['id' => 'refs', 'label' => 'Referencias (una por línea, opcional)', 'type' => 'area', 'full' => true, 'ph' => 'Ing. Juan Pérez – Jefe de Sistemas, Empresa X – 9xx xxx xxx'],
        ['id' => 'fecha', 'label' => 'Fecha', 'type' => 'date', 'def' => '@hoy'],
    ];
};
