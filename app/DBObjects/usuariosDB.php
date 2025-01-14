<?php
require_once __DIR__ . "/../models/conexion.php";

class UsuariosDB
{
    private $conexion;

    public function __construct()
    {
        $this->conexion = Conexion::getInstance();
    }
    /**
     * Relacionar un usuario con una planta
     * 
     * @param int $idPlanta El ID de la planta
     * @param int $idUsuario El ID del usuario
     * @param string $proveedor El nombre del proveedor
     * @return bool true en caso de éxito o false en caso de error
     */
    public function relacionarUsers($idPlanta, $idUsuario, $idProveedor)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            // Si idProveedor no es numérico, buscar su ID en la base de datos
            if (!is_numeric($idProveedor)) {
                $idProveedor = $this->obtenerIdProveedorPorNombre($idProveedor, $conn);
                if ($idProveedor == false) {
                    // Si no encontramos el ID del proveedor, devolvemos false
                    return false;
                }
            }

            $query = "INSERT INTO plantas_asociadas(usuario_id, planta_id, proveedor_id) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }

            // Vincula los parámetros: 'i' para enteros (usuario y planta) y 's' para string (proveedor)
            $stmt->bind_param('isi', $idUsuario, $idPlanta, $idProveedor);

            // Ejecuta la consulta
            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
                return false;
            }

            // Cierra la consulta y la conexión
            $stmt->close();

            return true;
        } catch (Exception $e) {
            error_log("Error al relacionar usuario y planta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Relacionar un usuario con una planta
     * 
     * @param int $idPlanta El ID de la planta
     * @param int $idUsuario El ID del usuario
     * @param string $proveedor El nombre del proveedor
     * @return bool true en caso de éxito o false en caso de error
     */
    public function desrelacionarUsers($idPlanta, $idUsuario, $idProveedor)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            // Si idProveedor no es numérico, buscar su ID en la base de datos
            if (!is_numeric($idProveedor)) {
                $idProveedor = $this->obtenerIdProveedorPorNombre($idProveedor, $conn);
                if ($idProveedor == false) {
                    // Si no encontramos el ID del proveedor, devolvemos false
                    return false;
                }
            }

            $query = "DELETE FROM `plantas_asociadas` WHERE planta_id = ? AND proveedor_id = ? AND usuario_id = ?;";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }

            $stmt->bind_param('iii', $idPlanta, $idProveedor, $idUsuario);

            if (!$stmt->execute()) {
                throw new Exception("Error en la ejecución de la consulta: " . $stmt->error);
            }

            // Verificar si se eliminaron filas
            if ($stmt->affected_rows === 0) {
                // No se ha eliminado ningún dato
                $stmt->close();
                return false;
            }

            // Cierra la consulta
            $stmt->close();

            return true;
        } catch (Exception $e) {
            error_log("Error al desrelacionar usuario y planta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener todos los usuarios
     * @return array|false Array con los usuarios o false en caso de error
     */
    public function getUsers($page = 1, $limit = 200)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            $offset = ($page - 1) * $limit; // Calcula el desplazamiento en base a la página actual

            $query = "SELECT 
                        usuarios.usuario_id,
                        usuarios.nombre AS nombre,
                        usuarios.apellido,
                        usuarios.email,
                        usuarios.movil,
                        usuarios.imagen,
                        usuarios.activo,
                        usuarios.eliminado,
                        clases.nombre AS clase,
                        usuarios.ultimo_login,
                        usuarios.empresa,
                        usuarios.direccion,
                        usuarios.ciudad,
                        usuarios.codigo_postal,
                        usuarios.region_estado,
                        usuarios.pais,
                        usuarios.cif_nif
                    FROM usuarios 
                    INNER JOIN clases ON usuarios.clase_id = clases.clase_id
                    LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ii', $limit, $offset); // Bind de los parámetros para LIMIT y OFFSET

            $stmt->execute();
            $result = $stmt->get_result();

            $usuarios = [];
            while ($row = $result->fetch_assoc()) {
                //quitamos la contraseña hasheada para enviar los datos
                if (isset($row['password_hash'])) {
                    unset($row['password_hash']);
                }
                $usuarios[] = $row;
            }

            $stmt->close();
            return $usuarios;
        } catch (Exception $e) {
            error_log("Error al obtener usuarios: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Obtener todos los usuarios
     * @return array|false Array con los usuarios o false en caso de error
     */
    public function getUser($id)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            $query = "SELECT
                        usuarios.usuario_id,
                        usuarios.nombre AS nombre,
                        usuarios.apellido,
                        usuarios.email,
                        usuarios.movil,
                        usuarios.imagen,
                        usuarios.activo,
                        usuarios.eliminado,
                        clases.nombre AS clase,
                        usuarios.ultimo_login,
                        usuarios.empresa,
                        usuarios.direccion,
                        usuarios.ciudad,
                        usuarios.codigo_postal,
                        usuarios.region_estado,
                        usuarios.pais,
                        usuarios.cif_nif
                    FROM usuarios
                    INNER JOIN clases ON usuarios.clase_id = clases.clase_id
                    WHERE usuarios.usuario_id = ?
                    ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id); // Vincula el parámetro $id como entero

            $stmt->execute();
            $result = $stmt->get_result();

            $usuario = $result->fetch_assoc(); // Obtiene una sola fila

            $stmt->close();

            return $usuario;
        } catch (Exception $e) {
            error_log("Error al obtener el usuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Agregar un nuevo usuario
     * @param array $data Datos del usuario a insertar
     * @return bool True en caso de éxito, false en caso de error
     */
    public function insertUser($data)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            // Campos obligatorios (ej. email y password)
            if (empty($data['email']) || empty($data['password'])) {
                // Puedes retornar un error o lanzar una excepción
                throw new Exception("Los campos 'email' y 'password' son obligatorios.");
            }

            // Generar el hash de la contraseña
            $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);

            // Para cada campo opcional, asignamos null si no está en $data:
            // (El operador null coalesce ?? permite asignar un valor por defecto si no existe la clave en $data)
            $clase          = $data['clase']          ?? 'usuario'; // Clase por defecto
            $nombre         = $data['nombre']         ?? null;
            $apellido       = $data['apellido']       ?? null;
            $imagen         = $data['imagen']         ?? null;
            $movil          = $data['movil']          ?? null;
            // Convertir booleanos a enteros (o dejar null si no viene):
            $activo         = isset($data['activo']) ? (int)$data['activo'] : 1;
            $eliminado      = isset($data['eliminado']) ? (int)$data['eliminado'] : 0;
            $ultimo_login   = $data['ultimo_login']   ?? null;
            $empresa        = $data['empresa']        ?? null;
            $direccion      = $data['direccion']      ?? null;
            $ciudad         = $data['ciudad']         ?? null;
            $codigo_postal  = $data['codigo_postal']  ?? null;
            $region_estado  = $data['region_estado']  ?? null;
            $pais           = $data['pais']           ?? null;
            $cif_nif        = $data['cif_nif']        ?? null;

            // Consulta de inserción
            // NOTA: si clase es opcional, la subconsulta (SELECT clase_id FROM clases WHERE nombre = ?)
            // debe permitir que se pase NULL (o que no exista). Puedes ajustar la lógica si "clase"
            // no siempre se envía.
            $query = "
            INSERT INTO usuarios (
                email,
                password_hash,
                clase_id,
                nombre,
                apellido,
                imagen,
                movil,
                activo,
                eliminado,
                ultimo_login,
                empresa,
                direccion,
                ciudad,
                codigo_postal,
                region_estado,
                pais,
                cif_nif
            ) 
            VALUES (
                ?,
                ?,
                (SELECT clase_id FROM clases WHERE nombre = ?), 
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ";

            $stmt = $conn->prepare($query);

            // Bind de parámetros (asegúrate de que el número y tipo de parámetros coincida)
            // s = string, i = integer, etc.
            $stmt->bind_param(
                'sssssssiissssssss',
                $data['email'],      // s
                $passwordHash,       // s
                $clase,              // s (busca clase_id por nombre)
                $nombre,             // s
                $apellido,           // s
                $imagen,             // s
                $movil,              // s
                $activo,             // i
                $eliminado,          // i
                $ultimo_login,       // s (guarda fecha/hora como string)
                $empresa,            // s
                $direccion,          // s
                $ciudad,             // s
                $codigo_postal,      // s
                $region_estado,      // s
                $pais,               // s
                $cif_nif             // s
            );

            // Ejecutar la consulta
            $result = $stmt->execute();

            // Cerrar el statement y la conexión
            $stmt->close();
            return $result;
        } catch (Exception $e) {
            error_log("Error al crear usuario: " . $e->getMessage());
            return false;
        }
    }


    /**
     * Actualizar un usuario existente
     * @param int $id ID del usuario a actualizar
     * @param array $data Datos del usuario a actualizar
     * @return bool True en caso de éxito, false en caso de error
     */
    public function updateUser($id, $data)
    {
        try {
            // Obtener conexión
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            if (!$conn) {
                throw new Exception("Conexión no disponible.");
            }

            // --------------------------------------------------
            // 1. Validar o forzar que cierto campo sea obligatorio
            // --------------------------------------------------
            if ($id === null) {
                throw new Exception("El campo 'id' es obligatorio para la actualización.");
            }

            // --------------------------------------------------
            // 2. Obtener el registro actual para preservar el password si no llega uno nuevo
            // --------------------------------------------------

            $oldPasswordHash = $this->getUserPassword($id);

            // Si mandan 'password', generamos un nuevo hash; de lo contrario, mantenemos el que existía.
            if (!empty($data['password'])) {
                $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
            } else {
                $passwordHash = $oldPasswordHash; // Se conserva la anterior
            }

            $usuario = $this->getUser($id);

            // --------------------------------------------------
            // 3. Asignar los campos opcionales con ?? null
            // --------------------------------------------------
            $email         = $data['email']          ?? $usuario['email']; // Mantener el email actual si no viene
            $clase         = $data['clase']          ?? $usuario['clase']; // Mantener la clase actual si no viene
            $nombre        = $data['nombre']         ?? $usuario['nombre']; // Mantener el nombre actual si no viene
            $apellido      = $data['apellido']       ?? $usuario['apellido']; // Mantener el apellido actual si no viene
            $imagen        = $data['imagen']         ?? $usuario['imagen']; // Mantener la imagen actual si no viene
            $movil         = $data['movil']          ?? $usuario['movil']; // Mantener el móvil actual si no viene
            $activo        = isset($data['activo']) ? (int)$data['activo'] : 1;
            $eliminado     = isset($data['eliminado']) ? (int)$data['eliminado'] : 0;
            $ultimo_login  = $data['ultimo_login']   ?? $usuario['ultimo_login']; // Mantener el último login actual si no viene
            $empresa       = $data['empresa']        ?? $usuario['empresa']; // Mantener la empresa actual si no viene
            $direccion     = $data['direccion']      ?? $usuario['direccion']; // Mantener la dirección actual si no viene
            $ciudad        = $data['ciudad']         ?? $usuario['ciudad']; // Mantener la ciudad actual si no viene
            $codigo_postal = $data['codigo_postal']  ?? $usuario['codigo_postal']; // Mantener el código postal actual si no viene
            $region_estado = $data['region_estado']  ?? $usuario['region_estado']; // Mantener la región/estado actual si no viene
            $pais          = $data['pais']           ?? $usuario['pais']; // Mantener el país actual si no viene
            $cif_nif       = $data['cif_nif']        ?? $usuario['cif_nif']; // Mantener el CIF/NIF actual si no viene

            // --------------------------------------------------
            // 4. Preparar la consulta de actualización
            // --------------------------------------------------
            // NOTA: si quieres "mantener" los valores viejos para los campos que no vengan,
            // en lugar de ponerlos a null, tendrías que cargar esos valores antes y usarlos aquí.
            $query = "
            UPDATE usuarios 
            SET
                email = ?,
                password_hash = ?,
                clase_id = (SELECT clase_id FROM clases WHERE nombre = ?),
                nombre = ?,
                apellido = ?,
                imagen = ?,
                movil = ?,
                activo = ?,
                eliminado = ?,
                ultimo_login = ?,
                empresa = ?,
                direccion = ?,
                ciudad = ?,
                codigo_postal = ?,
                region_estado = ?,
                pais = ?,
                cif_nif = ?
            WHERE usuario_id = ?
        ";

            $stmt = $conn->prepare($query);

            // 5. Vincular parámetros (18 placeholders + 1 para el WHERE)
            // Tipos: s (string), i (int). El orden debe coincidir con el de la sentencia.
            $stmt->bind_param(
                'sssssssiissssssssi',
                $email,     // s
                $passwordHash,      // s
                $clase,             // s
                $nombre,            // s
                $apellido,          // s
                $imagen,            // s
                $movil,             // s
                $activo,            // i
                $eliminado,         // i
                $ultimo_login,      // s
                $empresa,           // s
                $direccion,         // s
                $ciudad,            // s
                $codigo_postal,     // s
                $region_estado,     // s
                $pais,              // s
                $cif_nif,           // s
                $id                 // i (WHERE usuario_id = ?)
            );

            // --------------------------------------------------
            // 6. Ejecutar y cerrar
            // --------------------------------------------------
            $result = $stmt->execute();
            $stmt->close();

            return $result;
        } catch (Exception $e) {
            error_log("Error al actualizar usuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener la contraseña hasheada de un usuario por su ID
     * @param int $id El ID del usuario
     * @return string|null El password_hash o null si no se encuentra
     */
    public function getUserPassword($id)
    {
        try {
            // Obtenemos la conexión
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            // Consulta para obtener el password_hash
            $sql = "SELECT password_hash 
                FROM usuarios 
                WHERE usuario_id = ? 
                LIMIT 1";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $id); // 'i' porque usuario_id es entero
            $stmt->execute();

            // Forma 1: usando get_result() y fetch_assoc()
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $stmt->close();
                return $row['password_hash'];
            }

            // Si no encuentra fila
            $stmt->close();
            return null;
        } catch (Exception $e) {
            error_log("Error al obtener password del usuario: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Esta funcion "elimina" al user de manera logica es decir cambia el estado de la eliminacion del usuario
     * @param int $id ID del usuario a eliminar
     * @return bool True en caso de éxito, false en caso de error
     */
    public function borrarUser($id)
    {
        try {
            $conn = $this->conexion->getConexion();

            // Consulta para actualizar el estado eliminado a 1
            $query = "UPDATE usuarios SET eliminado = 1 WHERE usuario_id = ?";

            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id); // Liga el ID del usuario como parámetro
            $result = $stmt->execute();

            // Cerrar el statement y la conexión
            $stmt->close();

            return $result;
        } catch (Exception $e) {
            error_log("Error al realizar el borrado lógico del usuario: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Consultar un usuario
     * @param int $id ID del usuario a consultar
     * @return bool True en caso de que sea admin, false en caso de que no sea admin
     */
    public function getAdmin($id)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();
            $query = "SELECT clases.nombre as clase FROM usuarios
                      INNER JOIN clases ON clases.clase_id = usuarios.clase_id
                      WHERE usuarios.usuario_id = ?;";

            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();

            $isAdmin = false; // Variable para determinar si el usuario es admin

            if ($result && $row = $result->fetch_assoc()) {
                $clase = $row['clase'];

                // Verificar si el usuario es admin
                if (strtolower($clase) === 'admin') {
                    $isAdmin = true;
                }
            }

            // Cerrar el statement y la conexión
            $stmt->close();

            return $isAdmin; // Devolver el resultado

        } catch (Exception $e) {
            error_log("Error al obtener clase del usuario: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Comprueba que el email no exista en la base de datos
     *  @param string $email Email a verificar
     *  @return bool True en caso de que tenga un usuario con el mismo email, false en caso de que no tenga ese email en la base de datos
     */
    public function comprobarUsuario($email)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();
            $query = "SELECT COUNT(usuario_id) as usuarios FROM usuarios WHERE email = ? AND eliminado = 0;";

            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $row = $result->fetch_assoc()) {
                // Si el conteo es mayor que 0, el usuario existe
                if ($row['usuarios'] > 0) {
                    return true;
                }
            }

            // Cerrar el statement y la conexión
            $stmt->close();
            return false;
        } catch (Exception $e) {
            error_log("Error al comprobar usuario: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Comprueba que el email es el mismo que el del usuario que tiene el id
     *  @param string $email Email a verificar
     *  @return bool True en caso de que tenga un usuario con el mismo email, false en caso de que no tenga ese email en la base de datos
     */
    public function esMismoUsuario($id, $email)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            // Consulta para verificar si el email pertenece al usuario con el ID proporcionado
            $query = "SELECT usuario_id FROM usuarios WHERE email = ? AND usuario_id = ?";

            $stmt = $conn->prepare($query);
            $stmt->bind_param('si', $email, $id);
            $stmt->execute();
            $result = $stmt->get_result();

            // Si se encuentra un registro, el email pertenece al usuario con ese ID
            if ($result->num_rows > 0) {
                $stmt->close();
                return true;
            }

            // Cerrar el statement y la conexión si no se encuentra coincidencia
            $stmt->close();
            return false;
        } catch (Exception $e) {
            error_log("Error al verificar si el email pertenece al mismo usuario: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Comprueba que el email es el mismo que el del usuario que tiene el id
     *  @param string $email Email a verificar
     *  @return bool True en caso de que tenga un usuario con el mismo email, false en caso de que no tenga ese email en la base de datos
     */
    public function verificarEstadoUsuario($id)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            // Consulta para verificar el estado del usuario
            $query = "SELECT activo, eliminado FROM usuarios WHERE usuario_id = ?";

            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();

            // Verificar si se encontró un registro
            if ($result && $row = $result->fetch_assoc()) {
                $stmt->close();

                // Retornar el estado en función de los campos 'activo' y 'eliminado'
                if ($row['eliminado'] == 1 || $row['activo'] == 0) {
                    return false;
                } else {
                    return true;
                }
            }

            // Cerrar el statement y la conexión si no se encontró el usuario
            $stmt->close();
            return null; // O retorna un valor que indique que el usuario no existe

        } catch (Exception $e) {
            error_log("Error al verificar el estado del usuario: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Comprueba si el usuario esta eliminado
     *  @param int $id a verificar
     *  @return bool True en caso de que tenga un usuario, false en caso de que no tenga a ese usuario
     */
    public function usuarioEliminado($id)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            // Consulta para verificar el estado del usuario
            $query = "SELECT eliminado FROM usuarios WHERE usuario_id = ?";

            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();

            // Verificar si se encontró un registro
            if ($result && $row = $result->fetch_assoc()) {
                $stmt->close();

                // Retornar el estado en función del campo 'eliminado'
                if ($row['eliminado'] == 1) {
                    return false;
                } else {
                    return true;
                }
            }

            // Cerrar el statement y la conexión si no se encontró el usuario
            $stmt->close();
            return null; // O retorna un valor que indique que el usuario no existe

        } catch (Exception $e) {
            error_log("Error al verificar el estado del usuario: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Comprueba que la clase por ejemplo admin, usuario etc... existe en la base de datos
     *  @param string $clase verifica que la clase existe
     *  @return bool True en caso de que la clase exista, false en caso de que la clase no exista
     */
    public function comprobarClaseExiste($clase)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            // Consulta para verificar si la clase existe
            $query = "SELECT * FROM clases WHERE nombre = ?";

            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $clase); // Cambiamos a 's' para string
            $stmt->execute();
            $result = $stmt->get_result();

            // Verificar si se encontró un registro
            if ($result && $result->num_rows > 0) {
                $stmt->close();
                return true; // La clase existe
            }

            // Cerrar el statement y la conexión si no se encontró la clase
            $stmt->close();
            return false; // La clase no existe

        } catch (Exception $e) {
            error_log("Error al verificar si la clase existe: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Comprueba si un usuario está asociado a una planta dada, con un proveedor especificado.
     * Si $idProveedor no es numérico, se intenta obtener su ID por nombre.
     *
     * @param int $usuarioId   ID del usuario.
     * @param int $plantaId    ID de la planta.
     * @param mixed $idProveedor ID numérico del proveedor o nombre del proveedor.
     * @return bool Devuelve true si el usuario está asociado a la planta con ese proveedor, false en caso contrario.
     */
    public function comprobarUsuarioAsociadoPlanta($usuarioId, $plantaId, $idProveedor)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            // Si idProveedor no es numérico, buscar su ID en la base de datos
            if (!is_numeric($idProveedor)) {
                $idProveedor = $this->obtenerIdProveedorPorNombre($idProveedor, $conn);
                if ($idProveedor === false) {
                    // No se encontró el proveedor
                    return false;
                }
            }

            // Consulta para verificar si el usuario está asociado a la planta con ese proveedor
            $query = "SELECT * FROM plantas_asociadas WHERE planta_id = ? AND usuario_id = ? AND proveedor_id = ?";
            $stmt = $conn->prepare($query);

            // Asumiendo que planta_id, usuario_id e idProveedor son enteros
            $stmt->bind_param('sii', $plantaId, $usuarioId, $idProveedor);

            $stmt->execute();
            $result = $stmt->get_result();

            $asociado = ($result && $result->num_rows > 0);

            $stmt->close();
            return $asociado;
        } catch (Exception $e) {
            error_log("Error al verificar asociación de usuario y planta: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtener el ID del proveedor a partir de su nombre.
     * @param string $nombreProveedor El nombre del proveedor
     * @param mysqli $conn Conexión a la base de datos
     * @return int|false Retorna el ID del proveedor si existe, o false si no existe.
     */
    private function obtenerIdProveedorPorNombre($nombreProveedor, $conn)
    {
        try {
            $query = "SELECT id FROM proveedores WHERE nombre = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $nombreProveedor);
            $stmt->execute();
            $result = $stmt->get_result();
            $proveedor = $result->fetch_assoc();
            $stmt->close();

            return $proveedor ? (int)$proveedor['id'] : false;
        } catch (Exception $e) {
            error_log("Error al obtener ID del proveedor por nombre: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Actualiza el campo ultimo_login para un usuario en la base de datos.
     * 
     * @param int $usuarioId El ID del usuario
     * @return bool True en caso de éxito, false en caso de error
     */
    public function actualizarUltimoLogin($usuarioId)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            // Consulta para actualizar el campo ultimo_login
            $query = "UPDATE usuarios SET ultimo_login = NOW() WHERE usuario_id = ?";
            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }

            // Vincula el parámetro del ID de usuario
            $stmt->bind_param('i', $usuarioId);

            // Ejecuta la consulta
            $resultado = $stmt->execute();

            // Cierra la consulta y la conexión
            $stmt->close();

            return $resultado; // True si se ejecutó correctamente, false si no
        } catch (Exception $e) {
            error_log("Error al actualizar el último login: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Obtener id del usuario a través del email
     * @return array|false Array con los usuarios o false en caso de error
     */
    public function getIdUserPorEmail($email)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();


            $query = "SELECT usuarios.usuario_id FROM usuarios WHERE usuarios.email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $email); // Bind de los parámetros para LIMIT y OFFSET

            $stmt->execute();
            $result = $stmt->get_result();

            $idUsuario = $result->fetch_assoc(); // Obtiene una sola fila

            $stmt->close();
            return $idUsuario;
        } catch (Exception $e) {
            error_log("Error al obtener usuarios: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Cambiar la contraseña hasheada de un usuario por su ID
     * @param int $id El ID del usuario
     * @param string $password El nuevo password
     * @return true|false El estado de la operación
     */
    public function putUserPassword($id, $password)
    {
        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            // Obtenemos la conexión
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            // Consulta para reemplazar la password_hash
            $sql = "UPDATE usuarios 
                SET password_hash = ? 
                WHERE usuario_id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param('si', $passwordHash, $id); // 's' porque password_hash es string y 'i' porque usuario_id es entero
            $stmt->execute();

            // Validamos si hay cambios en la consulta
            if ($stmt->execute()) {
                $stmt->close();
                return true;
            } else {
                $stmt->close();
                return false;
            }
        } catch (Exception $e) {
            error_log("Error al obtener password del usuario: " . $e->getMessage());
            return null;
        }
    }
    /**
     * Obtener la imagen de un usuario por su ID
     * @param int $id El ID del usuario
     * @return string|null La URL de la imagen del usuario o null si no se encuentra
     */
    public function getUserImage($id)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            $query = "SELECT imagen FROM usuarios WHERE usuario_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->execute();

            $imagen = false;
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $imagen = $row['imagen'];
            }

            $stmt->close();
            return $imagen;
        } catch (Exception $e) {
            error_log("Error al obtener la imagen del usuario: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Eliminar la imagen de un usuario por su ID
     * @param int $id El ID del usuario
     * @return string|null La URL de la imagen del usuario o null si no se encuentra
     */
    public function deleteUserImage($id)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            $query = "UPDATE usuarios SET imagen = NULL WHERE usuario_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $id);
            $result = $stmt->execute();

            $stmt->close();
            return $result; // Devuelve true si se ejecutó correctamente, false si no
        } catch (Exception $e) {
            error_log("Error al obtener la imagen del usuario: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Modificar la imagen de un usuario por su ID
     * @param int $id El ID del usuario
     * @return true|false dependiendo si se ha modificado la imagen
     */
    public function putUserImage($id, $imagen)
    {
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();

            $query = "UPDATE usuarios SET imagen = ? WHERE usuario_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('si', $imagen, $id);
            $result = $stmt->execute();

            $stmt->close();
            return $result; // Devuelve true si se ejecutó correctamente, false si no
        } catch (Exception $e) {
            error_log("Error al actualizar la imagen del usuario: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Poner en null todas las imagenes de los usuarios que tengan la imagen pasada por parametro
     * @param int $id El ID del usuario
     * @return true|false dependiendo si se ha modificado la imagen
     */
    public function deleteUserImageByPath($path){
        try {
            $conexion = Conexion::getInstance();
            $conn = $conexion->getConexion();
            echo $path;
            $query = "UPDATE usuarios SET imagen = NULL WHERE imagen = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('s', $path);
            echo $query;
            $result = $stmt->execute();

            $stmt->close();
            return $result; // Devuelve true si se ejecutó correctamente, false si no
        } catch (Exception $e) {
            error_log("Error al actualizar la imagen del usuario: " . $e->getMessage());
            return false;
        }
    }
}
