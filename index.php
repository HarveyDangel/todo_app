<?php

require __DIR__ . '/vendor/autoload.php';

// require 'vendor/autoload.php';

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
use GraphQL\GraphQL;

// Connect to MySQL
$pdo = new PDO(
   "mysql:host=localhost:3306;dbname=todo_app;charset=utf8","root","");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Task type
$taskType = new ObjectType([
   'name' => 'Task',
   'fields' => [
      'id' => Type::nonNull(Type::int()),
      'title' => Type::nonNull(Type::string()),
      'done' => Type::nonNull(Type::boolean()),
   ],
]);

// Queries
$queryType = new ObjectType([
   'name' => 'Query',
   'fields' => [
      'tasks' => [
         'type' => Type::listOf($taskType),
         'resolve' => function () use ($pdo) {
            $stmt = $pdo->query("SELECT * FROM tasks ORDER BY id DESC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
         }
      ]
   ]
]);

// Mutations
$mutationType = new ObjectType([
   'name' => 'Mutation',
   'fields' => [
      'addTask' => [
         'type' => $taskType,
         'args' => [
            'title' => Type::nonNull(Type::string())
         ],
         'resolve' => function ($root, $args) use ($pdo) {
            $stmt = $pdo->prepare("INSERT INTO tasks (title) VALUES (?)");
            $stmt->execute([$args['title']]);
            $id = $pdo->lastInsertId();
            return ['id' => (int) $id, 'title' => $args['title'], 'done' => false];
         }
      ],
      'deleteTask' => [
         'type' => Type::string(),
         'args' => [
            'id' => Type::nonNull(Type::int())
         ],
         'resolve' => function ($root, $args) use ($pdo) {
            $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$args['id']]);
            return "Task deleted";
         }
      ],
      'toggleTask' => [
         'type' => $taskType,
         'args' => [
            'id' => Type::nonNull(Type::int())
         ],
         'resolve' => function ($root, $args) use ($pdo) {
            $stmt = $pdo->prepare("UPDATE tasks SET done = NOT done WHERE id = ?");
            $stmt->execute([$args['id']]);
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$args['id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
         }
      ]
   ]
]);

// Schema
$schema = new Schema([
   'query' => $queryType,
   'mutation' => $mutationType
]);

// Handle request
try {
   $rawInput = file_get_contents('php://input');
   $input = json_decode($rawInput, true);
   $query = $input['query'] ?? '';
   $variables = $input['variables'] ?? null;

   $result = GraphQL::executeQuery($schema, $query, null, null, $variables);
   $output = $result->toArray();
} catch (Throwable $e) {
   $output = ['errors' => [['message' => $e->getMessage()]]];
}

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
echo json_encode($output);

?>