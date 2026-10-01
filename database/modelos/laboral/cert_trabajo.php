<?php

return [
    'id' => 'cert_trabajo', 'nombre' => 'Certificado de trabajo', 'icono' => '🪪', 'cobro' => 'doc', 'seccion' => 'laboral', 'orden' => 41,
    'desc' => 'Certificado o constancia de trabajo: tiempo de servicios, cargo, funciones y conducta.',
    'nota' => 'El certificado de trabajo lo firma el empleador (gerente, titular o jefe de personal), de preferencia con sello. Si la persona sigue trabajando, se emite como «Constancia de trabajo». Por ley, el certificado no debe mencionar el motivo del cese salvo que el trabajador lo pida.',
    'campos' => [
        ['id' => 'tipoEmp', 'label' => 'El empleador es', 'type' => 'select', 'def' => 'empresa', 'opts' => ['empresa' => 'Una empresa (S.A.C., E.I.R.L., etc.)', 'natural' => 'Una persona natural con negocio']],
        ['id' => 'empresa', 'label' => 'Razón social o nombre comercial', 'full' => true, 'ph' => 'Ej.: Comercial Huallaga E.I.R.L.'],
        ['id' => 'ruc', 'label' => 'RUC del empleador', 'num' => true],
        ['id' => 'domEmp', 'label' => 'Domicilio del empleador', 'full' => true, 'ph' => 'Ej.: Av. Raimondi N° 520, Tingo María'],
        ['t' => 'h', 'label' => 'Quien firma'],
        ['id' => 'rep_trato', 'label' => 'Trato', 'type' => 'select', 'opts' => ['Don' => 'Don (varón)', 'Doña' => 'Doña (mujer)'], 'def' => 'Don'],
        ['id' => 'rep_nombre', 'label' => 'Nombres y apellidos', 'req' => true],
        ['id' => 'rep_dni', 'label' => 'DNI', 'num' => true, 'req' => true],
        ['id' => 'rep_cargo', 'label' => 'Cargo de quien firma', 'type' => 'select', 'def' => 'gg',
            'opts' => ['gg' => 'Gerente General', 'gerente' => 'Gerente', 'adm' => 'Administrador(a)', 'jrrhh' => 'Jefe(a) de Recursos Humanos', 'arrhh' => 'Administrador(a) de Recursos Humanos',
                'rl' => 'Representante Legal', 'titular' => 'Titular / Dueño(a) del negocio', 'encargado' => 'Encargado(a)', 'otro' => 'Otro cargo…']],
        ['id' => 'rep_cargoOtro', 'label' => 'Escribe el cargo', 'req' => true, 'si' => 'rep_cargo=otro', 'ph' => 'Ej.: Jefe de Personal, Supervisor General'],
        ['t' => 'persona', 'id' => 'tra', 'label' => 'Trabajador', 'sinEc' => true, 'sinDom' => true],
        ['t' => 'h', 'label' => 'El trabajo'],
        ['id' => 'cargo', 'label' => 'Cargo que desempeñó', 'req' => true],
        ['id' => 'area', 'label' => 'Área (opcional)'],
        ['id' => 'desde', 'label' => 'Desde', 'type' => 'date', 'req' => true],
        ['id' => 'sigue', 'label' => 'Todavía trabaja aquí (sale como Constancia de trabajo)', 'type' => 'check', 'def' => false, 'full' => true],
        ['id' => 'hasta', 'label' => 'Hasta', 'type' => 'date', 'req' => true, 'si' => '!sigue'],
        ['id' => 'funciones', 'label' => 'Funciones (opcional, una por línea)', 'type' => 'area', 'full' => true, 'modo' => 'lista', 'ph' => "Atención al cliente\nManejo de caja\nControl de inventario",
            'frases' => ['Atención al cliente', 'Manejo de caja y cuadre diario', 'Control de inventario y almacén', 'Ventas y cobranzas', 'Limpieza y mantenimiento del local', 'Conducción de vehículo de la empresa', 'Apoyo administrativo y archivo de documentos']],
        ['id' => 'conducta', 'label' => 'Cómo fue su desempeño', 'type' => 'select', 'def' => 'responsabilidad, honradez y dedicación en las labores encomendadas',
            'opts' => ['responsabilidad, honradez y dedicación en las labores encomendadas' => 'Responsable, honrado y dedicado', 'puntualidad, responsabilidad y un buen desempeño en sus funciones' => 'Puntual, responsable y buen desempeño',
                'eficiencia, iniciativa y espíritu de colaboración' => 'Eficiente, con iniciativa y colaborador', '' => 'No mencionar']],
    ],
    'plantilla' => <<<'B'
@php
    $nat = ($d['tipoEmp'] ?? '') === 'natural';
    $sigue = $h->on('sigue');
    $fr = ($d['rep_trato'] ?? '') === 'Doña';
    $ft = $h->fem('tra');
    $CG = ['gg' => ['Gerente General', 'Gerente General'], 'gerente' => ['Gerente', 'Gerente'], 'adm' => ['Administrador', 'Administradora'], 'jrrhh' => ['Jefe de Recursos Humanos', 'Jefa de Recursos Humanos'],
        'arrhh' => ['Administrador de Recursos Humanos', 'Administradora de Recursos Humanos'], 'rl' => ['Representante Legal', 'Representante Legal'], 'titular' => ['Titular', 'Titular'], 'encargado' => ['Encargado', 'Encargada']];
    $rc = ($d['rep_cargo'] ?? '') ?: ($nat ? 'titular' : 'gg');
    $cargoF = $rc === 'otro' ? $h->x('rep_cargoOtro') : ($CG[$rc][$fr ? 1 : 0] ?? $rc);
    $dur = \App\Redaccion\Ayudas::duracion($d['desde'] ?? null, $sigue ? ($d['fecha'] ?? today()->toDateString()) : ($d['hasta'] ?? null));
    $lugar = $nat ? 'mi negocio'.($h->hay('empresa') ? ' denominado **'.$h->up('empresa').'**' : '') : 'nuestra empresa';
    $intro = ($fr ? 'La que suscribe, ' : 'El que suscribe, ').'**'.$h->up('rep_nombre').'**, identificad'.($fr ? 'a' : 'o').' con DNI N° '.$h->x('rep_dni')
        .($nat ? ($rc === 'titular' ? '' : ', en calidad de '.$cargoF.($h->hay('empresa') ? ' de **'.$h->up('empresa').'**' : ' del negocio')).', con RUC N° '.$h->x('ruc').', con domicilio en '.$h->x('domEmp')
               : ', en calidad de '.$cargoF.' de **'.$h->up('empresa').'**, con RUC N° '.$h->x('ruc').', con domicilio en '.$h->x('domEmp')).':';
@endphp
@if ($h->hay('empresa'))
[center] **{{ $h->up('empresa') }}**
[center] RUC N° {{ $h->x('ruc') }}{{ $h->hay('domEmp') ? ' – '.$h->v('domEmp') : '' }}
@endif
[title] {{ $sigue ? 'CONSTANCIA DE TRABAJO' : 'CERTIFICADO DE TRABAJO' }}
[p] {{ $intro }}
[left] **{{ $sigue ? 'HACE CONSTAR:' : 'CERTIFICA:' }}**
[p] Que, {{ $ft ? 'doña' : 'don' }} **{{ $h->up('tra_nombre') }}**, identificad{{ $ft ? 'a' : 'o' }} con DNI N° {{ $h->x('tra_dni') }}, {{ $sigue ? 'labora en '.$lugar.' desde el '.$h->fecha('desde').' hasta la fecha' : 'ha laborado en '.$lugar.' desde el '.$h->fecha('desde').' hasta el '.$h->fecha('hasta') }}{{ $dur ? ', acumulando un tiempo de servicios de '.$dur : '' }}, desempeñando el cargo de **{{ $h->up('cargo') }}**{{ $h->hay('area') ? ' en el área de '.$h->v('area') : '' }}.
@if ($h->lineas('funciones'))
[p] {{ $sigue ? 'Tiene' : 'Tuvo' }} a su cargo las siguientes funciones:
@foreach ($h->lineas('funciones') as $fx)
[li] – {{ $fx }}
@endforeach
@endif
@if ($h->hay('conducta'))
[p] Durante su permanencia {{ $sigue ? 'viene demostrando' : 'demostró' }} {{ $h->v('conducta') }}.
@endif
[p] Se expide {{ $sigue ? 'la presente constancia' : 'el presente certificado' }} a solicitud {{ $ft ? 'de la interesada' : 'del interesado' }}, para los fines que estime conveniente.
[right] {{ $h->x('ciudad') }}, {{ $h->fecha('fecha') }}.
{{ \App\Redaccion\Ayudas::firmaTxt($h->up('rep_nombre'), $h->x('rep_dni'), $cargoF.' – Firma y sello', false) }}
B,
];
